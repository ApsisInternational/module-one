<?php

namespace Apsis\One\Service;

use Apsis\One\Controller\Router;
use Apsis\One\Logger\Logger;
use Apsis\One\Service\Sub\SubAbandonedService;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as CronCollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Throwable;

class AbandonedService extends AbstractCronService
{
    const CART_CONTENT_ENDPOINT = Router::API_PREFIX . 'abandoned/cart';
    const CHECKOUT_ENDPOINT = Router::API_PREFIX . 'abandoned/checkout';
    const UPDATER_URL = 'apsis/abandoned/helper';

    /**
     * @var QuoteCollectionFactory
     */
    private QuoteCollectionFactory $quoteCollectionFactory;

    /**
     * @var SubAbandonedService
     */
    private SubAbandonedService $subAbandonedService;

    /**
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $writer
     * @param CronCollectionFactory $cronCollectionFactory
     * @param ModuleListInterface $moduleList
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param SubAbandonedService $subAbandonedService
     */
    public function __construct(
        Logger $logger,
        StoreManagerInterface $storeManager,
        WriterInterface $writer,
        CronCollectionFactory $cronCollectionFactory,
        ModuleListInterface $moduleList,
        QuoteCollectionFactory $quoteCollectionFactory,
        SubAbandonedService $subAbandonedService
    ) {
        parent::__construct($logger, $storeManager, $writer, $cronCollectionFactory, $moduleList);
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->subAbandonedService = $subAbandonedService;
    }

    /**
     * @return QuoteCollection
     */
    private function getQuoteCollection(): QuoteCollection
    {
        return $this->quoteCollectionFactory->create();
    }

    /**
     * @inheritDoc
     */
    protected function getEntityCronJobCode(): string
    {
        return 'apsis_one_find_abandoned_carts';
    }

    /**
     * @inheritDoc
     */
    protected function runEntityCronjobTaskByStore(StoreInterface $store): void
    {
        try {
            $acDelayPeriod = $this->getStoreConfig($store, BaseService::PATH_CONFIG_AC_DURATION);
            if (! $acDelayPeriod) {
                return;
            }

            $quoteCollection = $this->getQuoteCollectionByStore($store, $acDelayPeriod);
            if ($quoteCollection && $quoteCollection->getSize()) {
                $this->subAbandonedService->aggregateCartsData($quoteCollection);
            }
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return;
        }
    }

    /**
     * @param StoreInterface $store
     * @param string $acDelayPeriod
     *
     * @return Collection|boolean
     */
    private function getQuoteCollectionByStore(StoreInterface $store, string $acDelayPeriod): bool|Collection
    {
        try {
            $interval = $this->getDateIntervalFromIntervalSpec(sprintf('PT%sM', $acDelayPeriod));
            $fromTime = $this->getDateTimeFromTimeAndTimeZone()->sub($interval);
            $toTime = clone $fromTime;
            $fromTime->sub($this->getDateIntervalFromIntervalSpec('PT5M'));
            $updated = [
                'from' => $fromTime->format('Y-m-d H:i:s'),
                'to' => $toTime->format('Y-m-d H:i:s'),
                'date' => true,
            ];
            return $this->getQuoteCollection()
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('items_count', ['gt' => 0])
                ->addFieldToFilter('customer_email', ['notnull' => true])
                ->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.updated_at', $updated);
        } catch (Throwable $e) {
            $this->logError(__METHOD__, $e);
            return false;
        }
    }
}
