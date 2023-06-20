<?php

namespace Apsis\One\Controller\Api;

use Apsis\One\Controller\AbstractAction;
use Apsis\One\Controller\Api\ProfileLists\Records;
use Apsis\One\Service\BaseService;
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
    const REQUIRE_BODY = ['POST', 'PATCH'];

    /**
     * @var BaseService|ProfileService|WebhookService
     */
    protected BaseService|ProfileService|WebhookService $service;

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
            // Get all params
            $this->queryParams = (array) $this->getRequest()->getQuery();
            $httpMethod = $this->getRequest()->getMethod();
            $actionMethod = $this->getRequest()->getParam('actionMethod');
            if (in_array($httpMethod, self::REQUIRE_BODY)) {
                $this->requestBody = json_decode((string) $this->getRequest()->getContent(), true);
            }

            // Validate request http method allowed
            if (! in_array($httpMethod, $this->allowedHttpMethods[$actionMethod] ?? [])) {
                return $this->sendErrorInResponse(405);
            }

            // Single classMethod for both HEAD and GET
            if ($httpMethod === 'HEAD') {
                $httpMethod = 'GET';
            }

            // Validate query params
            $classMethod = strtolower($httpMethod) . $actionMethod;
            if (! in_array($httpMethod, self::REQUIRE_BODY) &&
                (! empty(array_diff_key($this->requiredParams[$classMethod]['query'], $this->queryParams)) ||
                ! $this->validateParamType($this->requiredParams[$classMethod]['query'], $this->queryParams))
            ) {
                return $this->sendErrorInResponse(400);
            }

            // Validate body
            if (in_array($httpMethod, self::REQUIRE_BODY) &&
                (! empty(array_diff_key($this->requiredParams[$classMethod]['post'], $this->requestBody)) ||
                    ! $this->validateParamType($this->requiredParams[$classMethod]['post'], $this->requestBody))
            ) {
                return $this->sendErrorInResponse(400);
            }

            // Authenticate key
            $RequestApiKey = $this->getRequest()->getHeader('X-Api-Key');
            $ConfigEncryptedKey = $this->service
                ->getStoreConfig($this->service->getStore(0), BaseService::PATH_CONFIG_API_KEY);
            $ConfigUnencryptedKey = $ConfigEncryptedKey ? $this->encryptor->decrypt($ConfigEncryptedKey) : null;
            if (empty($RequestApiKey) || empty($ConfigUnencryptedKey) || $ConfigUnencryptedKey !== $RequestApiKey) {
                return $this->sendErrorInResponse(401);
            }

            // Validate if store exist using store code
            $store = $this->service->getStore($this->getRequest()->getParam('storeCode'));
            if (! $store instanceof StoreInterface) {
                return $this->sendErrorInResponse(404);
            }
            $this->store = $store;

            // Set task id if required
            if ($this->isTaskIdRequired) {
                $taskId = $this->getRequest()->getParam('taskId');
                if (($this instanceof Records && ! is_numeric($taskId)) ||
                    (! $this instanceof Records && empty($taskId))
                ) {
                    return $this->sendErrorInResponse(400);
                }
                $this->taskId = $taskId;
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
     * @param array $requiredParams
     * @param array $requestParam
     *
     * @return bool
     */
    private function validateParamType(array $requiredParams, array $requestParam): bool
    {
        $types = ['int', 'string', 'array', 'bool'];
        foreach ($requiredParams as $param => $type) {
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
        // Default type
        $type = 'string';

        switch ($input) {
            case 'text':
            case 'gallery':
            case 'media_image':
            case 'image':
            case 'textarea':
            case 'multiselect':
            case 'select':
                $type = 'string';
                break;

            case 'date':
            case 'datetime':
                $type = 'integer';
                break;

            case 'boolean':
                $type = 'boolean';
                break;

            case 'price':
            case 'weight':
                $type = 'double';
                break;

            default:
                break;
        }

        return $type;
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
        return $this->getResponse()
            ->setStatusHeader($httpCode, null, $phrase)
            ->setBody($body)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true)
            ->setHeader('Content-Type', 'application/json', true);
    }
}
