<?php

namespace Apsis\One\Controller\Api;

use Apsis\One\Controller\AbstractAction;
use Apsis\One\Controller\Api\ProfileLists\Records;
use Apsis\One\Service\BaseService;
use Apsis\One\Service\ConfigService;
use Apsis\One\Service\ProfileService;
use Apsis\One\Service\WebhookService;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Controller\Api\Profiles\Index as ProfilesIndex;
use Throwable;

abstract class AbstractApi extends AbstractAction
{
    /**
     * @var BaseService|ProfileService|WebhookService|ConfigService
     */
    protected BaseService|ProfileService|WebhookService|ConfigService $service;

    /**
     * @var StoreInterface
     */
    protected StoreInterface $store;

    /**
     * @var CustomerFactory
     */
    protected CustomerFactory $customerFactory;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $encryptor;

    /**
     * @var bool
     */
    protected bool $isTaskIdRequired = false;

    /**
     * @var string
     */
    protected string $taskId;

    /**
     * @var array
     */
    protected array $allowedHttpMethods = [];

    /**
     * @var array
     */
    protected array $requiredParams = [];

    /**
     * @var array
     */
    protected array $queryParams = [];

    /**
     * @var array
     */
    protected array $requestBody = [];

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param BaseService $service
     * @param CustomerFactory $customerFactory
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        BaseService $service,
        CustomerFactory $customerFactory,
        EncryptorInterface $encryptor
    ) {
        $this->customerFactory = $customerFactory;
        $this->service = $service;
        $this->encryptor = $encryptor;
        parent::__construct($request, $response, $service);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResponseInterface
    {
        try {
            $this->logRequestResponse($this->getRequest());

            $httpMethod = (string) $this->getRequest()->getMethod();
            $httpMethod = $httpMethod === 'HEAD' ? 'GET' : $httpMethod;
            $actionMethod = (string) $this->getRequest()->getParam('actionMethod');
            $classMethod = strtolower($httpMethod) . $actionMethod;
            $storeCode = (string) $this->getRequest()->getParam('storeCode');

            if (! $this->isAllNeededDataExist($httpMethod, $actionMethod, $classMethod, $storeCode)) {
                return $this->sendErrorInResponse(500);
            }

            if (! $this->isRequestAuthentic()) {
                return $this->sendErrorInResponse(401);
            }

            if (! in_array($httpMethod, $this->allowedHttpMethods[$actionMethod])) {
                return $this->sendErrorInResponse(405);
            }

            if (! $this->isStoreExist($storeCode)) {
                return $this->sendErrorInResponse(400);
            }

            if (! $this->validateTaskId()) {
                return $this->sendErrorInResponse(400);
            }

            if (! $this->validateParams($classMethod, 'query')) {
                return $this->sendErrorInResponse(400);
            }

            if (in_array($httpMethod, ['POST', 'PATCH']) && ! $this->validateParams($classMethod, 'post')) {
                return $this->sendErrorInResponse(400);
            }

            return call_user_func([$this, $classMethod]);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return $this->sendErrorInResponse(500);
        }
    }

    /**
     * @return Customer
     */
    protected function getCustomerModel(): Customer
    {
        return $this->customerFactory->create();
    }

    /**
     * @param string $storeCode
     *
     * @return bool
     */
    protected function isStoreExist(string $storeCode): bool
    {
        $store = $this->service->getStore($storeCode);
        if (! $store instanceof StoreInterface) {
            return false;
        }
        $this->store = $store;
        return true;
    }

    /**
     * @return bool
     */
    protected function isRequestAuthentic(): bool
    {
        $RequestApiKey = $this->getRequest()->getHeader('X-Api-Key');
        $ConfigEncryptedKey = $this->service
            ->getStoreConfig($this->service->getStore(0), BaseService::PATH_CONFIG_API_KEY);
        $ConfigUnencryptedKey = $ConfigEncryptedKey ? $this->encryptor->decrypt($ConfigEncryptedKey) : null;
        if (empty($RequestApiKey) || empty($ConfigUnencryptedKey) || $ConfigUnencryptedKey !== $RequestApiKey) {
            return false;
        }
        return true;
    }

    /**
     * @param string $httpMethod
     * @param string $actionMethod
     * @param string $classMethod
     * @param string $storeCode
     *
     * @return bool
     */
    protected function isAllNeededDataExist(
        string $httpMethod,
        string $actionMethod,
        string $classMethod,
        string $storeCode
    ): bool {
        if (empty($httpMethod) || empty($actionMethod) || empty($classMethod) || empty($storeCode) ||
            ! isset($this->allowedHttpMethods[$actionMethod]) || ! isset($this->requiredParams[$classMethod]) ||
            ! method_exists($this, $classMethod)
        ) {
            $this->service->debug(
                'Not everything exist',
                [
                    'httpMethod' => $httpMethod,
                    'actionMethod' => $actionMethod,
                    'classMethod' => $classMethod,
                    'storeCode' => $storeCode,
                    'allowedHttpMethods' => $this->allowedHttpMethods,
                    'requiredParams' => $this->requiredParams,
                    'class methods list' => get_class_methods($this)
                ]
            );
            return false;
        }
        return true;
    }

    /**
     * @param string $classMethod
     * @param string $type
     *
     * @return bool
     */
    protected function validateParams(string $classMethod, string $type): bool
    {
        $requestParam = [];
        if ($type === 'query') {
            $this->queryParams = $requestParam = (array) $this->getRequest()->getQuery();
        } elseif ($type === 'post') {
            $body = json_decode((string) $this->getRequest()->getContent(), true);
            if (is_array($body) && ! empty($body)) {
                $this->requestBody = $requestParam = $body;
            }
        }

        if (! empty(array_diff_key($this->requiredParams[$classMethod][$type], $requestParam))) {
            return false;
        }

        $types = ['int', 'string', 'array', 'bool'];
        foreach ($this->requiredParams[$classMethod][$type] as $param => $type) {
            if (! in_array($type, $types)) {
                return false;
            }

            if ($type === 'int' && (! is_numeric($requestParam[$param]) || $requestParam[$param] < 0)) {
                return false;
            }

            if ($type === 'string' && (! is_string($requestParam[$param]) || ! strlen($requestParam[$param]))) {
                return false;
            }

            if ($type === 'array') {
                if (! is_array($requestParam[$param]) || empty($requestParam[$param])) {
                    return false;
                }
            }

            if ($type === 'bool' && ! is_bool($requestParam[$param])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function validateTaskId(): bool
    {
        $taskId = (string) $this->getRequest()->getParam('taskId');
        if ($this->isTaskIdRequired && ! empty($taskId)) {
            if ($this instanceof Records && ! is_numeric($taskId)) {
                return false;
            }
            $this->taskId = $taskId;
        }
        return true;
    }

    /**
     * @return array
     */
    protected function getCustomerAttributes(): array
    {
        try {
            $exclude = array_merge(
                ProfilesIndex::EXCLUDE_CUSTOMER_ATTRIBUTES,
                array_keys(ProfilesIndex::SCHEMA)
            );
            $customerAttributes = [];
            foreach ($this->getCustomerModel()->getAttributes() as $attribute) {
                if ($label = $attribute->getFrontendLabel()) {
                    $code = $attribute->getAttributeCode();
                    if (in_array($code, $exclude)) {
                        continue;
                    }

                    $customerAttributes[$code] = [
                        'code_name' => $code,
                        'type' => $this->getBackendTypeByInput((string) $attribute->getFrontendInput()),
                        'display_name' => BaseService::escapeQuote($label)
                    ];
                }
            }
            return $customerAttributes;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param string $input
     *
     * @return string
     */
    protected function getBackendTypeByInput(string $input): string
    {
        return match ($input) {
            'date', 'datetime' => 'integer',
            'boolean' => 'boolean',
            'price', 'weight' => 'double',
            default => 'string',
        };
    }

    /**
     * @param int|null $httpCode
     *
     * @return ResponseInterface
     */
    protected function sendErrorInResponse(int $httpCode = null): ResponseInterface
    {
        return $this->sendResponse($httpCode ?: $this->getRequest()->getParam('httpCode'));
    }

    /**
     * @param int $httpCode
     * @param string|null $phrase
     * @param string|null $body
     *
     * @return ResponseInterface
     */
    protected function sendResponse(int $httpCode, string $phrase = null, string $body = null): ResponseInterface
    {
        $response = $this->getResponse()
            ->setStatusHeader($httpCode, null, $phrase)
            ->setBody($body)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true)
            ->setHeader('Content-Type', 'application/json', true);

        $this->logRequestResponse($response);

        return $response;
    }

    /**
     * @param ResponseInterface|RequestInterface $object
     *
     * @return void
     */
    protected function logRequestResponse(ResponseInterface|RequestInterface $object): void
    {
        if (getenv('APSIS_DEVELOPER')) {
            $this->service->debug(
                $object instanceof ResponseInterface? 'API Response' : 'API Request',
                [str_replace(PHP_EOL, PHP_EOL . '        ', PHP_EOL . $object->toString())]
            );
        }
    }
}
