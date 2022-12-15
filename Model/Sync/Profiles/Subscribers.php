<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ProfileBatch;
use Apsis\One\Model\ProfileBatchFactory;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\Collection;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\File as ApsisFileHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers\SubscriberFactory as SubscriberDataFactory;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Subscribers implements ProfileSyncInterface
{
    const LIMIT = 5000;
    const CONSENT_TYPE_OPT_IN = 'opt-in';
    const CONSENT_TYPE_OPT_OUT = 'opt-out';

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
    private $keySpace;

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
        try {
            $this->apsisCoreHelper = $apsisCoreHelper;

            $section = (string) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::MAPPINGS_SECTION_SECTION
            );
            $topics = (string) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_TOPIC
            );
            $mappings = $this->apsisConfigHelper->getSubscriberAttributeMapping($store);
            $this->keySpace = $this->apsisCoreHelper->getKeySpaceDiscriminator($section);

            // Validate all things compulsory
            if (! $section || ! $topics || empty($mappings) || ! isset($mappings['email']) || ! $this->keySpace) {
                return;
            }

            $limit = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::PROFILE_SYNC_SUBSCRIBER_BATCH_SIZE
            );
            if (empty($limit)) {
                $limit = self::LIMIT;
            }

            $syncStatus = [Profile::SYNC_STATUS_PENDING, Profile::SYNC_STATUS_SUBSCRIBER_PENDING_UPDATE];

            //Subscribers : opt-in
            $collectionOptIn = $this->profileCollectionFactory->create()
                ->getSubscribersToBatchByStore($store->getId(), $limit, Subscriber::STATUS_SUBSCRIBED, $syncStatus);

            if ($collectionOptIn->getSize()) {
                $this->batchSubscribersForStore(
                    $collectionOptIn,
                    $store,
                    $mappings,
                    $topics,
                    self::CONSENT_TYPE_OPT_IN,
                    $section
                );
            }

            //Subscribers : opt-out
            $collectionOptOut = $this->profileCollectionFactory->create()
                ->getSubscribersToBatchByStore($store->getId(), $limit, Subscriber::STATUS_UNSUBSCRIBED, $syncStatus);

            if ($collectionOptOut->getSize()) {
                $this->batchSubscribersForStore(
                    $collectionOptOut,
                    $store,
                    $mappings,
                    $topics,
                    self::CONSENT_TYPE_OPT_OUT,
                    $section
                );
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param Collection $collection
     * @param StoreInterface $store
     * @param array $mappings
     * @param string $topics
     * @param string $consentType
     * @param string $section
     */
    private function batchSubscribersForStore(
        Collection $collection,
        StoreInterface $store,
        array $mappings,
        string $topics,
        string $consentType,
        string $section
    ) {
        try {
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());
            if (! $client) {
                return;
            }

            $attributesArrWithVersionId = $this->apsisCoreHelper->getAttributeVersionIds($client, $section);
            if (empty($attributesArrWithVersionId)) {
                return;
            }

            $this->createCsvForStore(
                $store,
                $collection,
                $mappings,
                $topics,
                $attributesArrWithVersionId,
                $consentType
            );
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param Collection $collection
     * @param array $mappings
     * @param string $topics
     * @param array $attributesArrWithVersionId
     * @param string $consentType
     */
    private function createCsvForStore(
        StoreInterface $store,
        Collection $collection,
        array $mappings,
        string $topics,
        array $attributesArrWithVersionId,
        string $consentType
    ) {
        try {
            $topicsMapping = $this->getTopicArrFromString($topics);
            $profileDataArr = $this->getProfileDataArr($collection);
            if (empty($topicsMapping) || empty($profileDataArr)) {
                return;
            }

            $jsonMappings = $this->apsisConfigHelper->getJsonMappingData(
                $this->keySpace,
                $mappings,
                $attributesArrWithVersionId,
                $topicsMapping,
                $consentType
            );
            $mappings = array_merge([Profile::INTEGRATION_KEYSPACE => Profile::INTEGRATION_KEYSPACE], $mappings);

            $file = $this->createFileWithHeaders(
                $store,
                $consentType,
                array_merge(array_keys($mappings), array_values($topicsMapping))
            );

            if (empty($file)) {
                $info = [
                    'Message' => 'Unable to create file',
                    'Store Id' => $store->getId(),
                    'Consent type', $consentType
                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);

                return;
            }

            $subscriberCollection = $this->getSubscribersFromIdsByStore(
                $store,
                $collection->getColumnValues('subscriber_id')
            );
            if (empty($subscriberCollection)) {
                return;
            }

            $subscribersToUpdate = [];
            foreach ($subscriberCollection as $subscriber) {
                if (! empty($profileDataArr[$subscriber->getSubscriberId()])) {
                    try {
                        $profileData = $profileDataArr[$subscriber->getSubscriberId()];
                        $subscriber->setIntegrationUid($profileData['integration_uid']);
                        $subscriber->setProfileKey($profileData['integration_uid']);

                        $subscriberData = $this->getSubscriberDataForCsvRow(
                            array_keys($mappings),
                            $subscriber,
                            array_values($topicsMapping),
                            $profileData['consent']
                        );

                        $this->apsisFileHelper->outputCSV($file, $subscriberData);
                        $subscribersToUpdate[] = $subscriber->getSubscriberId();
                    } catch (Throwable $e) {
                        $this->apsisCoreHelper->logError(__METHOD__, $e);
                        $this->apsisCoreHelper->log(__METHOD__ . ': Skipped subscriber with id :' .
                            $subscriber->getSubscriberId());

                        continue;
                    }
                }

                //clear collection and free memory
                $subscriber->clearInstance();
            }

            if (empty($subscribersToUpdate) && strlen($file)) {
                $this->apsisFileHelper->deleteFile($file);
            } elseif (! empty($subscribersToUpdate)) {
                $this->registerBatchItem($file, $store, $subscribersToUpdate, $jsonMappings);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);

            if (! empty($subscribersToUpdate)) {
                $this->apsisCoreHelper->debug(
                    __METHOD__,
                    ['Skipped subscribers with ids' => implode(',', $subscribersToUpdate)]
                );
            }
        }
    }

    /**
     * @param string $topics
     *
     * @return array
     */
    private function getTopicArrFromString(string $topics)
    {
        $topicsArr = explode(',', $topics);
        $topicsMapping = [];

        foreach ($topicsArr as $topicMapping) {
            $topicMapping = explode('|', (string) $topicMapping);
            $topicsMapping[] = $topicMapping[0];
        }

        return $topicsMapping;
    }

    /**
     * @param Collection $collection
     *
     * @return array
     */
    private function getProfileDataArr(Collection $collection)
    {
        $profileDataArr = [];

        foreach ($collection as $profile) {
            $profileDataArr[$profile->getSubscriberId()] = [
                'integration_uid' => $profile->getIntegrationUid(),
                'consent' => (int) $profile->getSubscriberSyncStatus() === Profile::SYNC_STATUS_PENDING
            ];
        }

        return $profileDataArr;
    }

    /**
     * @param StoreInterface $store
     * @param string $consentType
     * @param array $headers
     *
     * @return string
     */
    private function createFileWithHeaders(StoreInterface $store, string $consentType, array $headers)
    {
        try {
            $file = strtolower($store->getCode() . '_subscriber_' . $consentType . '_' . date('d_m_Y_His') . '.csv');
            $this->apsisFileHelper->outputCSV($file, $headers);

            return $file;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return '';
        }
    }

    /**
     * @param array $mappings
     * @param Subscriber $subscriber
     * @param array $topics
     * @param int $consent
     *
     * @return array
     */
    private function getSubscriberDataForCsvRow(
        array $mappings,
        Subscriber $subscriber,
        array $topics,
        int $consent
    ) {
        return $this->subscriberDataFactory->create()
            ->setModelData($mappings, $subscriber, $this->apsisCoreHelper)
            ->setConsentTopicData($topics, $consent)
            ->toCSVArray();
    }

    /**
     * @param string $file
     * @param StoreInterface $store
     * @param array $ids
     * @param array $jsonMappings
     */
    private function registerBatchItem(
        string $file,
        StoreInterface $store,
        array $ids,
        array $jsonMappings
    ) {
        try {
            $filePath = $this->apsisFileHelper->getFilePath($file);
            $this->profileBatchFactory->create()
                ->registerBatchItem(
                    $store->getId(),
                    $filePath,
                    ProfileBatch::BATCH_TYPE_SUBSCRIBER,
                    implode(',', $ids),
                    $this->apsisCoreHelper->serialize($jsonMappings)
                );

            $this->profileResource->updateSubscribersSyncStatus(
                $ids,
                Profile::SYNC_STATUS_BATCHED,
                $this->apsisCoreHelper
            );

            $info = [
                'Total Profiles Batched' => count($ids),
                'Store Id' => $store->getId()
            ];
            $this->apsisCoreHelper->debug(__METHOD__, $info);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @param StoreInterface $store
     * @param $subscriberIds
     *
     * @return array|SubscriberCollection
     */
    private function getSubscribersFromIdsByStore(StoreInterface $store, $subscriberIds)
    {
        try {
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
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }
}
