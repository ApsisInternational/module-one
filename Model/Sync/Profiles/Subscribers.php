<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Profile\Collection;
use \Exception;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Helper\File as ApsisFileHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers\SubscriberFactory as SubscriberDataFactory;
use Apsis\One\Model\Profile;
use Magento\Newsletter\Model\Subscriber as MagentoSubscriber;
use Apsis\One\Model\Sync\Profiles;

class Subscribers
{
    const LIMIT = 500;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ApsisConfigHelper
     */
    private $apsisConfigHelper;

    /**
     * @var ApsisFileHelper
     */
    private $apsisFileHelper;

    /**
     * @var ProfileResource
     */
    private $profileResource;

    /**
     * @var SubscriberCollectionFactory
     */
    private $subscriberCollectionFactory;

    /**
     * @var SubscriberDataFactory
     */
    private $subscriberDataFactory;

    /**
     * Subscribers constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param ApsisConfigHelper $apsisConfigHelper
     * @param ApsisFileHelper $apsisFileHelper
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param SubscriberDataFactory $subscriberDataFactory
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        ApsisConfigHelper $apsisConfigHelper,
        ApsisFileHelper $apsisFileHelper,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        SubscriberDataFactory $subscriberDataFactory
    ) {
        $this->subscriberDataFactory = $subscriberDataFactory;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->apsisFileHelper = $apsisFileHelper;
        $this->apsisConfigHelper = $apsisConfigHelper;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
    }

    /**
     * @param StoreInterface $store
     */
    public function sync(StoreInterface $store)
    {
        $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED
        );
        $topics = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
        );
        $mappings = $this->apsisConfigHelper->getSubscriberAttributeMapping($store);

        if ($sync && $topics && ! empty($mappings) && isset($mappings['email'])) {
            $limit = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_SUBSCRIBER_BATCH_SIZE
            );
            $collection = $this->profileCollectionFactory->create()
                ->getSubscribersToSyncByStore($store->getId(), ($limit) ? $limit : self::LIMIT);

            if ($collection->getSize()) {
                try {
                    $integrationIdsArray = $this->getIntegrationIdsArray($collection);
                    $file = strtolower($store->getCode() . '_subscriber_' . date('d_m_Y_His') . '.csv');
                    $mappings = array_merge(Profiles::DEFAULT_HEADERS, $mappings);
                    $this->apsisFileHelper->outputCSV(
                        $file,
                        $mappings
                    );

                    $subscriberCollection = $this->getSubscribersFromIdsByStore(
                        $store,
                        $collection->getColumnValues('subscriber_id')
                    );
                    $subscribersToUpdate = [];

                    /** @var MagentoSubscriber $subscriber */
                    foreach ($subscriberCollection as $subscriber) {
                        try {
                            $subscriber->setIntegrationUid($integrationIdsArray[$subscriber->getSubscriberId()]);
                            $subscriberData = $this->subscriberDataFactory->create()
                                ->setSubscriberData(array_keys($mappings), $subscriber)
                                ->toCSVArray();
                            $this->apsisFileHelper->outputCSV(
                                $file,
                                $subscriberData
                            );
                            $subscribersToUpdate[] = $subscriber->getSubscriberId();
                        } catch (Exception $e) {
                            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                            $this->apsisCoreHelper->log(
                                'Skipped subscriber with id :' . $subscriber->getSubscriberId()
                            );
                        }

                        //clear collection and free memory
                        $subscriber->clearInstance();
                    }
                    $filePath = $this->apsisFileHelper->getFilePath($file);
                    $this->apsisCoreHelper->log('Subscriber file : ' . $filePath);
                    /** @ToDo send file to import profile api */

                    $updated = $this->profileResource->updateSubscribersSyncStatus(
                        $subscribersToUpdate,
                        $store->getId(),
                        Profile::SYNC_STATUS_SYNCED
                    );

                    $this->apsisCoreHelper->log('Total subscriber synced : ' . $updated);
                } catch (Exception $e) {
                    $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage());
                }
            }
        }
    }

    /**
     * @param Collection $collection
     *
     * @return array
     */
    private function getIntegrationIdsArray(Collection $collection)
    {
        $integrationIdsArray = [];
        foreach ($collection as $item) {
            $integrationIdsArray[$item->getSubscriberId()] = $item->getIntegrationUid();
        }
        return $integrationIdsArray;
    }

    /**
     * @param StoreInterface $store
     * @param $subscriberIds
     *
     * @return SubscriberCollection
     */
    private function getSubscribersFromIdsByStore(StoreInterface $store, $subscriberIds)
    {
        $collection = $this->subscriberCollectionFactory->create()
            ->addFieldToFilter('main_table.subscriber_id', ['in' => $subscriberIds])
            ->addStoreFilter($store->getId());

        $collection->getSelect()
            ->joinLeft(
                ['store' => $collection->getTable('store')],
                "main_table.store_id = store.store_id",
                ['store_name' => 'name', 'website_id']
            )->joinLeft(
                ['website' => $collection->getTable('store_website')],
                "website.website_id = store.website_id",
                ['website_name' => 'name']
            );

        return $collection;
    }
}
