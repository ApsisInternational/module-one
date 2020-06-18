<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\ProfileBatchFactory;
use Apsis\One\Model\ResourceModel\Profile\Collection;
use \Exception;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\File as ApsisFileHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers\SubscriberFactory as SubscriberDataFactory;
use Apsis\One\Model\Profile;
use Magento\Newsletter\Model\Subscriber as MagentoSubscriber;
use Apsis\One\Model\ProfileBatch;
use Magento\Store\Model\ScopeInterface;

class Subscribers implements ProfileSyncInterface
{
    const LIMIT = 5000;

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
     * @var ProfileBatchFactory
     */
    private $profileBatchFactory;

    /**
     * @var string
     */
    private $keySpaceDiscriminator;

    /**
     * @var string
     */
    private $sectionDiscriminator;

    /**
     * Subscribers constructor.
     *
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param ProfileResource $profileResource
     * @param ApsisConfigHelper $apsisConfigHelper
     * @param ApsisFileHelper $apsisFileHelper
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     * @param SubscriberDataFactory $subscriberDataFactory
     * @param ProfileBatchFactory $profileBatchFactory
     */
    public function __construct(
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource,
        ApsisConfigHelper $apsisConfigHelper,
        ApsisFileHelper $apsisFileHelper,
        SubscriberCollectionFactory $subscriberCollectionFactory,
        SubscriberDataFactory $subscriberDataFactory,
        ProfileBatchFactory $profileBatchFactory
    ) {
        $this->subscriberDataFactory = $subscriberDataFactory;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->apsisFileHelper = $apsisFileHelper;
        $this->apsisConfigHelper = $apsisConfigHelper;
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->profileBatchFactory = $profileBatchFactory;
    }

    /**
     * @param StoreInterface $store
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function processForStore(StoreInterface $store, ApsisCoreHelper $apsisCoreHelper)
    {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        );
        $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED
        );
        $topics = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
        );
        $mappings = $this->apsisConfigHelper->getSubscriberAttributeMapping($store);
        $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());

        if ($client && $sync && $this->sectionDiscriminator && strlen($topics) && ! empty($mappings) &&
            isset($mappings['email'])
        ) {
            $attributesArrWithVersionId = $this->apsisCoreHelper
                ->getAttributesArrWithVersionId($client, $this->sectionDiscriminator);
            $this->keySpaceDiscriminator = $this->apsisCoreHelper
                ->getKeySpaceDiscriminator($this->sectionDiscriminator);
            $topics = explode(',', $topics);

            if (empty($topics) || empty($attributesArrWithVersionId)) {
                return;
            }

            $limit = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_CONFIGURATION_PROFILE_SYNC_SUBSCRIBER_BATCH_SIZE
            );

            //Subscribers : opt-in
            $this->batchSubscribersForStore(
                $store,
                ($limit) ? $limit : self::LIMIT,
                $mappings,
                $topics,
                $attributesArrWithVersionId,
                Subscriber::STATUS_SUBSCRIBED,
                'opt-in'
            );

            //Subscribers : opt-out
            $this->batchSubscribersForStore(
                $store,
                ($limit) ? $limit : self::LIMIT,
                $mappings,
                $topics,
                $attributesArrWithVersionId,
                Subscriber::STATUS_UNSUBSCRIBED,
                'opt-out'
            );
        }
    }

    /**
     * @param StoreInterface $store
     * @param int $limit
     * @param array $mappings
     * @param array $topics
     * @param array $attributesArrWithVersionId
     * @param int $subscriberStatus
     * @param string $consentType
     */
    private function batchSubscribersForStore(
        StoreInterface $store,
        int $limit,
        array $mappings,
        array $topics,
        array $attributesArrWithVersionId,
        int $subscriberStatus,
        string $consentType
    ) {
        $collection = $this->profileCollectionFactory->create()
            ->getSubscribersToBatchByStore(
                $store->getId(),
                $limit,
                $subscriberStatus
            );

        if ($collection->getSize()) {
            $this->createCsvForStore(
                $store,
                $collection,
                $mappings,
                $topics,
                $attributesArrWithVersionId,
                $consentType
            );
        }
    }

    /**
     * @param StoreInterface $store
     * @param Collection $collection
     * @param array $mappings
     * @param array $topics
     * @param array $attributesArrWithVersionId
     * @param string $consentType
     */
    private function createCsvForStore(
        StoreInterface $store,
        Collection $collection,
        array $mappings,
        array $topics,
        array $attributesArrWithVersionId,
        string $consentType
    ) {
        try {
            $integrationIdsArray = $this->getIntegrationIdsArray($collection);
            $file = strtolower($store->getCode() . '_subscriber_'. $consentType . '_' . date('d_m_Y_His') . '.csv');

            $topicsMapping = [];
            foreach ($topics as $topic) {
                $topic = explode('|', $topic);
                $topicsMapping[$topic[2]] = [$topic[0], $topic[1]];
            }

            $jsonMappings = $this->apsisConfigHelper->getJsonMappingData(
                $this->keySpaceDiscriminator,
                $mappings,
                $attributesArrWithVersionId,
                $topicsMapping,
                $consentType
            );

            $mappings = array_merge([Profile::INTEGRATION_KEYSPACE => Profile::INTEGRATION_KEYSPACE], $mappings);
            $this->apsisFileHelper->outputCSV($file, array_merge(array_keys($mappings), array_keys($topicsMapping)));

            $subscriberCollection = $this->getSubscribersFromIdsByStore(
                $store,
                $collection->getColumnValues('subscriber_id')
            );
            $subscribersToUpdate = [];

            /** @var MagentoSubscriber $subscriber */
            foreach ($subscriberCollection as $subscriber) {
                if (isset($integrationIdsArray[$subscriber->getSubscriberId()])) {
                    try {
                        $subscriber->setIntegrationUid($integrationIdsArray[$subscriber->getSubscriberId()]);
                        $subscriberData = $this->subscriberDataFactory->create()
                            ->setModelData(array_keys($mappings), $subscriber, $this->apsisCoreHelper)
                            ->setConsentListData(array_keys($topicsMapping))
                            ->toCSVArray();
                        $this->apsisFileHelper->outputCSV($file, $subscriberData);
                        $subscribersToUpdate[] = $subscriber->getSubscriberId();
                    } catch (Exception $e) {
                        $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
                        $this->apsisCoreHelper->log(__METHOD__ . ': Skipped subscriber with id :' .
                            $subscriber->getSubscriberId());
                        continue;
                    }
                }

                //clear collection and free memory
                $subscriber->clearInstance();
            }

            if (! empty($subscribersToUpdate)) {
                $filePath = $this->apsisFileHelper->getFilePath($file);
                $this->profileBatchFactory->create()
                    ->registerBatchItem(
                        $store->getId(),
                        $filePath,
                        ProfileBatch::BATCH_TYPE_SUBSCRIBER,
                        implode(',', $subscribersToUpdate),
                        $this->apsisCoreHelper->serialize($jsonMappings)
                    );
                $this->profileResource->updateSubscribersSyncStatus(
                    $subscribersToUpdate,
                    $store->getId(),
                    Profile::SYNC_STATUS_BATCHED,
                    $this->apsisCoreHelper
                );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            if (! empty($subscribersToUpdate)) {
                $this->apsisCoreHelper->log(__METHOD__ . ': Skipped subscribers with id :' .
                    implode(',', $subscribersToUpdate));
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
