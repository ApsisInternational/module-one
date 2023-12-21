<?php

namespace Apsis\One\Model\Adminhtml\Config\Backend;

use Apsis\One\Model\ResourceModel\EventResource;
use Apsis\One\Service\BaseService;
use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class HistoricalEventBackendModel extends InvalidateDataSaveBackendModel
{
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;

    /**
     * @var BaseService
     */
    private BaseService $service;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var EventResource
     */
    private EventResource $eventResource;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ManagerInterface $messageManager
     * @param BaseService $service
     * @param RequestInterface $request
     * @param EventResource $eventResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ManagerInterface $messageManager,
        BaseService $service,
        RequestInterface $request,
        EventResource $eventResource,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->messageManager = $messageManager;
        $this->service = $service;
        $this->request = $request;
        $this->eventResource = $eventResource;
    }

    /**
     * @inheirtDoc
     */
    public function beforeSave(): static
    {
        try {
            $value = $this->getValue();
            $storeId = $this->request->getParam(StoreManagerInterface::CONTEXT_STORE);
            if ($storeId && $value) {
                // Save record update value and datetime
                $this->service->saveStoreConfig(
                    $this->service->getStore($storeId),
                    [
                        BaseService::PATH_CONFIG_EVENT_PREVIOUS_HISTORICAL =>
                            sprintf('%s MONTH, SET ON %s', $value, $this->service->formatCurrentDateToInternalFormat())
                    ]
                );
                // Set pending status on historical events for store given period
                $this->eventResource
                    ->setPendingStatusOnHistoricalEvents($storeId, $this->getDuration($value), $this->service);
                $text = __('DURATION FOR HISTORICAL EVENT SYNC IS SET TO %s MONTH.
               EVENTS (IF EXIST FOR GIVEN PERIOD) WILL START SYNCING IN FEW MINUTES.');
                $this->messageManager->addNoticeMessage(sprintf($text, $value));
            } else {
                $this->messageManager->addNoticeMessage(__('NO CHANGE IS MADE TO DURATION FOR HISTORICAL EVENT SYNC'));
            }
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            $this->messageManager->addErrorMessage(__('UNABLE TO COMPLETE REQUEST, FOR MORE INFORMATION SEE LOGS.'));
        }
        return parent::beforeSave();
    }

    /**
     * @param string $value
     *
     * @return array
     *
     * @throws Exception
     */
    private function getDuration(string $value): array
    {
        $interval = $this->service->getDateIntervalFromIntervalSpec(sprintf('P%sM', $value));
        $toTime = $this->service->getDateTimeFromTimeAndTimeZone();
        $fromTime = clone $toTime;
        $fromTime = $fromTime->sub($interval);
        return [
            'from' => $fromTime->format('Y-m-d H:i:s'),
            'to' => $toTime->format('Y-m-d H:i:s'),
        ];
    }
}
