<?php

namespace Apsis\One\Controller;

use Apsis\One\Service\AbandonedService;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\App\Route\ConfigInterface;
use Magento\Framework\App\Router\Base;
use Magento\Framework\Code\NameBuilder;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Router\ActionList;
use Magento\Framework\App\Router\PathConfigInterface;
use Apsis\One\Service\BaseService;
use Throwable;

class Router extends Base
{
    const DEFAULT_ROUTER_PARAMS = ['storeCode', 'moduleFrontName', 'area', 'actionPath'];
    const API_PREFIX = 'apsisendpoint/v1/';
    const API_STATIC_PATH_TO_ACTION_MAP = [
        self::API_PREFIX . 'system_information' => [
            'actionPath' => 'api_systeminformation',
            'action' => 'index',
            'method' => 'SystemInformation',
            'requiredRouteParams' => []
        ],
        self::API_PREFIX . 'system_information/config' => [
            'actionPath' => 'api_systeminformation',
            'action' => 'index',
            'method' => 'Config',
            'requiredRouteParams' => ['action']
        ],
        self::API_PREFIX . 'profile_entities' => [
            'actionPath' => 'api_profiles',
            'action' => 'index',
            'method' => 'ProfileEntities',
            'requiredRouteParams' => []
        ],
        self::API_PREFIX . 'schema/profile' => [
            'actionPath' => 'api_profiles',
            'action' => 'index',
            'method' => 'ProfileSchema',
            'requiredRouteParams' => ['action']
        ],
        self::API_PREFIX . 'records/profile' => [
            'actionPath' => 'api_profiles',
            'action' => 'index',
            'method' => 'ProfileRecords',
            'requiredRouteParams' => ['action']
        ],
        self::API_PREFIX . 'records/profile/count' => [
            'actionPath' => 'api_profiles',
            'action' => 'index',
            'method' => 'ProfileRecordsCount',
            'requiredRouteParams' => ['action', 'task']
        ],
        self::API_PREFIX . 'consent_bases/profile' => [
            'actionPath' => 'api_consents',
            'action' => 'index',
            'method' => 'ProfileConsentBases',
            'requiredRouteParams' => ['action']
        ],
        self::API_PREFIX . 'consents/profile' => [
            'actionPath' => 'api_consents',
            'action' => 'index',
            'method' => 'ProfileConsents',
            'requiredRouteParams' => ['action']
        ],
        self::API_PREFIX . 'consents/profile/count' => [
            'actionPath' => 'api_consents',
            'action' => 'index',
            'method' => 'ProfileConsentsCount',
            'requiredRouteParams' => ['action', 'task']
        ],
        self::API_PREFIX . 'profile_lists' => [
            'actionPath' => 'api_profilelists',
            'action' => 'index',
            'method' => 'ProfileLists',
            'requiredRouteParams' => []
        ],
        self::API_PREFIX . 'webhooks/records/profile' => [
            'actionPath' => 'api_webhooksrecords',
            'action' => 'index',
            'method' => 'ProfileRecordsWebhook',
            'requiredRouteParams' => ['action', 'task']
        ],
        self::API_PREFIX . 'webhooks/consents/profile' => [
            'actionPath' => 'api_webhooksconsents',
            'action' => 'index',
            'method' => 'ProfileConsentsWebhook',
            'requiredRouteParams' => ['action', 'task']
        ],
        self::API_PREFIX . 'webhooks/records' => [
            'actionPath' => 'api_webhooksrecords',
            'action' => 'index',
            'method' => 'AllRecordsWebhook',
            'requiredRouteParams' => ['action']
        ],
        self::API_PREFIX . 'webhooks/consents' => [
            'actionPath' => 'api_webhooksconsents',
            'action' => 'index',
            'method' => 'AllConsentsWebhook',
            'requiredRouteParams' => ['action']
        ]
    ];
    const API_DYNAMIC_PATH_TO_ACTION_MAP = [
        self::API_PREFIX . 'webhooks/records/subscriptions/' => [
            'actionPath' => 'api_webhooksrecords',
            'action' => 'subscription',
            'method' => 'ProfileRecordsWebhook',
            'requiredRouteParams' => ['action', 'task', 'taskId']
        ],
        self::API_PREFIX . 'webhooks/consents/subscriptions/' => [
            'actionPath' => 'api_webhooksconsents',
            'action' => 'subscription',
            'method' => 'ProfileConsentsWebhook',
            'requiredRouteParams' => ['action', 'task', 'taskId']
        ],
        self::API_PREFIX . 'profile_lists/profile/' => [
            'subActions' => [
                'records' => [
                    'actionPath' => 'api_profilelists',
                    'action' => 'records',
                    'method' => 'ProfileListsRecords',
                    'requiredRouteParams' => ['action', 'taskId', 'task']
                ],
                'count' => [
                    'actionPath' => 'api_profilelists',
                    'action' => 'records',
                    'method' => 'ProfileListsRecordsCount',
                    'requiredRouteParams' => ['action', 'taskId', 'task', 'subTask']
                ]
            ]
        ]
    ];
    const INTERNAL_STATIC_PATH_TO_ACTION_MAP = [
        AbandonedService::CART_CONTENT_ENDPOINT => [
            'actionPath' => 'frontend_abandoned',
            'action' => 'cart'
        ],
        AbandonedService::CHECKOUT_ENDPOINT => [
            'actionPath' => 'frontend_abandoned',
            'action' => 'checkout'
        ],
        AbandonedService::UPDATER_URL => [
            'actionPath' => 'frontend_abandoned',
            'action' => 'helper'
        ],
    ];

    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @param ActionList $actionList
     * @param ActionFactory $actionFactory
     * @param DefaultPathInterface $defaultPath
     * @param ResponseFactory $responseFactory
     * @param ConfigInterface $routeConfig
     * @param UrlInterface $url
     * @param NameBuilder $nameBuilder
     * @param PathConfigInterface $pathConfig
     * @param BaseService $baseService
     */
    public function __construct(
        ActionList $actionList,
        ActionFactory $actionFactory,
        DefaultPathInterface $defaultPath,
        ResponseFactory $responseFactory,
        ConfigInterface $routeConfig,
        UrlInterface $url,
        NameBuilder $nameBuilder,
        PathConfigInterface $pathConfig,
        BaseService $baseService
    ) {
        parent::__construct(
            $actionList,
            $actionFactory,
            $defaultPath,
            $responseFactory,
            $routeConfig,
            $url,
            $nameBuilder,
            $pathConfig
        );
        $this->baseService = $baseService;
    }

    /**
     * @inerhitDoc
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        try {
            // If path is related to old links
            $requestPath = trim($request->getPathInfo(), '/');
            foreach (self::INTERNAL_STATIC_PATH_TO_ACTION_MAP as $path => $config) {
                if (str_contains($requestPath, $path)) {
                    return $this->forwardRequest($request, $config, []);
                }
            }

            // If not an APSIS API endpoint
            if (! str_contains($requestPath, self::API_PREFIX)) {
                return parent::match($request);
            }

            // No match found
            $match = $this->findMatchAndGetConfig($request);
            if (empty($match)) {
                return $this->forwardError($request);
            }

            // Set full route params based on endpoint config
            $this->_requiredParams = array_merge(self::DEFAULT_ROUTER_PARAMS, $match['requiredRouteParams']);
            $routeParams = $this->parseRequest($request);

            // If path params do not match
            if (! $this->isRequiredAndParsedRouteParamsMatches($routeParams)) {
                return $this->forwardError($request);
            }

            // Validate storeCode, always required
            if (empty($routeParams['storeCode'])) {
                return $this->forwardError($request);
            }

            // Validate taskId, if required and need to be a number
            if (in_array('taskId', $this->_requiredParams) && (empty($routeParams['taskId']))) {
                return $this->forwardError($request);
            }

            return $this->forwardRequest($request, $match, $routeParams);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
            return parent::match($request);
        }
    }

    /**
     * @param RequestInterface $request
     *
     * @return ActionInterface
     */
    private function forwardError(RequestInterface $request): ActionInterface
    {
        $config = [
            'actionPath' => 'api',
            'action' => 'error',
            'httpCode' => 400
        ];
        return $this->forwardRequest($request, $config, []);
    }

    /**
     * @param RequestInterface $request
     * @param array $config
     * @param array $routeParams
     *
     * @return ActionInterface
     */
    private function forwardRequest(RequestInterface $request, array $config, array $routeParams): ActionInterface
    {
        try {
            $request->setModuleName('apsis');
            $request->setControllerModule('apsis');
            $request->setRouteName($this->_routeConfig->getRouteByFrontName('apsis'));
            $request->setControllerName($config['actionPath']);
            $request->setActionName($config['action']);

            if (isset($config['method'])) {
                $request->setParam('actionMethod', $config['method']);
            }
            if (isset($routeParams['storeCode'])) {
                $request->setParam('storeCode', $routeParams['storeCode']);
            }
            if (isset($routeParams['taskId'])) {
                $request->setParam('taskId', $routeParams['taskId']);
            }
            if (isset($config['httpCode'])) {
                $request->setParam('httpCode', $config['httpCode']);
            }

            return $this->actionFactory->create(Forward::class);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
            return parent::match($request);
        }
    }

    /**
     * @param array $routeParams
     *
     * @return bool
     */
    private function isRequiredAndParsedRouteParamsMatches(array $routeParams): bool
    {
        // There should not be any difference, from required and parsed request.
        if (empty(array_diff($this->_requiredParams, array_keys($routeParams))) &&
            empty(array_diff(array_keys($routeParams), $this->_requiredParams))
        ) {
            return true;
        }
        return false;
    }

    /**
     * @param RequestInterface $request
     *
     * @return array
     */
    private function findMatchAndGetConfig(RequestInterface $request): array
    {
        try {
            // Set default route params
            $this->_requiredParams = self::DEFAULT_ROUTER_PARAMS;
            $routeParams = $this->parseRequest($request);

            // Variables are not required at this stage of validation
            if (isset($routeParams['variables'])) {
                foreach ($routeParams['variables'] as $key => $value) {
                    if ($key) {
                        $variables[] = $key;
                    }
                    if ($value) {
                        $variables[] = $value;
                    }
                }
                unset($routeParams['variables']);
            }

            // If default route params do not match
            if (! $this->isRequiredAndParsedRouteParamsMatches($routeParams)) {
                return [];
            }

            // Store code is not required at this stage of validation
            unset($routeParams['storeCode']);
            if (! empty($variables)) {
                $routeParams = array_merge(array_values($routeParams), array_values($variables));
            }

            $configsMap = [];
            $identifier = implode('/', array_values($routeParams));

            // First see if there is a match from static paths
            if (isset(self::API_STATIC_PATH_TO_ACTION_MAP[$identifier])) {
                $configsMap = self::API_STATIC_PATH_TO_ACTION_MAP[$identifier];
            } else {
                foreach (self::API_DYNAMIC_PATH_TO_ACTION_MAP as $path => $config) {
                    if (str_contains($identifier, $path) && ! isset($config['subActions'])) {
                        $configsMap = $config;
                    } elseif (str_contains($identifier, $path) && isset($config['subActions'])) {
                        $parts = explode('/', $identifier);
                        $lastIndex = array_pop($parts);
                        if (isset($config['subActions'][$lastIndex])) {
                            $configsMap = $config['subActions'][$lastIndex];
                        }
                    }
                }
            }
            return $configsMap;
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
            return [];
        }
    }
}
