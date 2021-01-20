<?php

namespace Apsis\One\Model\Sync\Profiles;

use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\ProfileBatchFactory;
use Apsis\One\Model\ResourceModel\Profile\Collection;
use Exception;
use Magento\Framework\Exception\FileSystemException;
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
    private $keySpaceDiscriminator;

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
            $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
            );
            $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENABLED
            );
            $topics = (string) $this->apsisCoreHelper->getStoreConfig(
                $store,
                ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC
            );
            $mappings = $this->apsisConfigHelper->getSubscriberAttributeMapping($store);
            $client = $this->apsisCoreHelper->getApiClient(ScopeInterface::SCOPE_STORES, $store->getId());

            if ($client && $sync && $sectionDiscriminator && strlen($topics) && ! empty($mappings) &&
                isset($mappings['email'])
            ) {
                $attributesArrWithVersionId = $this->apsisCoreHelper
                    ->getAttributesArrWithVersionId($client, $sectionDiscriminator);
                $this->keySpaceDiscriminator = $this->apsisCoreHelper
                    ->getKeySpaceDiscriminator($sectionDiscriminator);

                if (empty($attributesArrWithVersionId)) {
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
                    self::CONSENT_TYPE_OPT_IN
                );

                //Subscribers : opt-out
                $this->batchSubscribersForStore(
                    $store,
                    ($limit) ? $limit : self::LIMIT,
                    $mappings,
                    $topics,
                    $attributesArrWithVersionId,
                    Subscriber::STATUS_UNSUBSCRIBED,
                    self::CONSENT_TYPE_OPT_OUT
                );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param StoreInterface $store
     * @param int $limit
     * @param array $mappings
     * @param string $topics
     * @param array $attributesArrWithVersionId
     * @param int $subscriberStatus
     * @param string $consentType
     */
    private function batchSubscribersForStore(
        StoreInterface $store,
        int $limit,
        array $mappings,
        string $topics,
        array $attributesArrWithVersionId,
        int $subscriberStatus,
        string $consentType
    ) {
        try {
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
            $profileDataArr = $this->getProfileDataArr($collection, $topicsMapping, $consentType);
            if (! empty($topicsMapping) && ! empty($profileDataArr)) {
                $jsonMappings = $this->createMapping(
                    [],
                    $mappings,
                    $attributesArrWithVersionId,
                    $topicsMapping,
                    $consentType
                );

                $mappings = array_merge([Profile::INTEGRATION_KEYSPACE => Profile::INTEGRATION_KEYSPACE], $mappings);
                $file = $this->createFileWithHeaders(
                    $store,
                    '',
                    $consentType,
                    array_merge(array_keys($mappings), array_keys($topicsMapping))
                );

                $subscriberCollection = $this->getSubscribersFromIdsByStore(
                    $store,
                    $collection->getColumnValues('subscriber_id')
                );
                $subscribersToUpdate = [];
                $subscribersToUpdateForSecondaryOperation = [];
                $secondaryFile = '';
                $secondaryMapping = [];
                $fileHeadersForSecondaryOperation = array_merge(
                    [Profile::INTEGRATION_KEYSPACE, Profile::EMAIL_FIELD],
                    array_keys($topicsMapping)
                );

                /** @var MagentoSubscriber $subscriber */
                foreach ($subscriberCollection as $subscriber) {
                    if (! empty($profileDataArr[$subscriber->getSubscriberId()])) {
                        try {
                            $profileData = $profileDataArr[$subscriber->getSubscriberId()];
                            $subscriber->setIntegrationUid($profileData['integration_uid']);
                            $subscriber->setProfileKey($profileData['integration_uid']);

                            //Main operation for both opt-in and opt-out consents
                            if (! empty($profileData['consent_topics']['main_function'])) {
                                $subscriberData = $this->getSubscriberDataForCsvRow(
                                    array_keys($mappings),
                                    $subscriber,
                                    $profileData['consent_topics']['main_function']
                                );
                                $this->apsisFileHelper->outputCSV($file, $subscriberData);
                                $subscribersToUpdate[] = $subscriber->getSubscriberId();
                            }

                            //Secondary operation out of opt-in op where profile's has opt-out of certain topics
                            if ($consentType == self::CONSENT_TYPE_OPT_IN &&
                                ! empty($profileData['consent_topics']['secondary_function'])) {
                                $secondaryFile = $this->createFileWithHeaders(
                                    $store,
                                    $secondaryFile,
                                    $consentType . '_' . self::CONSENT_TYPE_OPT_OUT,
                                    $fileHeadersForSecondaryOperation
                                );
                                $secondaryMapping = $this->createMapping(
                                    $secondaryMapping,
                                    [Profile::EMAIL_FIELD => $mappings[Profile::EMAIL_FIELD]],
                                    $attributesArrWithVersionId,
                                    $topicsMapping,
                                    self::CONSENT_TYPE_OPT_OUT
                                );
                                $secondarySubscriberData = $this->getSubscriberDataForCsvRow(
                                    [Profile::INTEGRATION_KEYSPACE, Profile::EMAIL_FIELD],
                                    $subscriber,
                                    $profileData['consent_topics']['secondary_function']
                                );
                                $this->apsisFileHelper->outputCSV($secondaryFile, $secondarySubscriberData);
                                $subscribersToUpdateForSecondaryOperation[] = $subscriber->getSubscriberId();
                            }
                        } catch (Exception $e) {
                            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
                    $this->registerBatchItem($file, $store, $subscribersToUpdate, $jsonMappings, $consentType, $topics);
                }

                if (empty($subscribersToUpdateForSecondaryOperation) && strlen($secondaryFile)) {
                    $this->apsisFileHelper->deleteFile($secondaryFile);
                } elseif (! empty($subscribersToUpdateForSecondaryOperation)) {
                    $this->registerBatchItem(
                        $secondaryFile,
                        $store,
                        $subscribersToUpdateForSecondaryOperation,
                        $secondaryMapping
                    );
                }
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            if (! empty($subscribersToUpdate)) {
                $this->apsisCoreHelper->log(__METHOD__ . ': Skipped subscribers with ids :' .
                    implode(',', $subscribersToUpdate));
            }
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            return [];
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
            $topicMapping = explode('|', $topicMapping);
            $topicsMapping[$topicMapping[1]] = $topicMapping[0];
        }
        return $topicsMapping;
    }

    /**
     * @param Collection $collection
     * @param array $topicsMapping
     * @param string $consentType
     *
     * @return array
     */
    private function getProfileDataArr(Collection $collection, array $topicsMapping, string $consentType)
    {
        $profileDataArr = [];
        if (! empty($topicsMapping)) {
            /** @var Profile $item */
            foreach ($collection as $item) {
                $profileDataArr[$item->getSubscriberId()] = [
                    'integration_uid' => $item->getIntegrationUid(),
                    'consent_topics' => $this->getConsentTopicsForProfile($item, $topicsMapping, $consentType)
                ];
            }
        }
        return $profileDataArr;
    }

    /**
     * @param Profile $item
     * @param array $topicsMapping
     * @param string $consentType
     *
     * @return array
     */
    private function getConsentTopicsForProfile(Profile $item, array $topicsMapping, string $consentType)
    {
        $profileConsentArr = ['main_function' => [], 'secondary_function' => []];
        if ($consentType == self::CONSENT_TYPE_OPT_IN) { // Possible scenario's for opt-in
            if (strlen($item->getTopicSubscription()) > 1) { //Existing topic subscription exist on profile
                $profileTopicArr = $this->getTopicArrFromString($item->getTopicSubscription());
                $profileConsentArr = [
                    'main_function' => $this->formatConsentListArrayForCsv($topicsMapping, $profileTopicArr),
                    'secondary_function' => $this->formatConsentListArrayForCsv($topicsMapping, $profileTopicArr, true)
                ];
            } elseif ($item->getTopicSubscription() === null) { //First time, should be subscribed to all in selection
                $profileConsentArr['main_function'] = $this->formatConsentListArrayForCsv($topicsMapping, [], true);
            } elseif ($item->getTopicSubscription() === '-') { //Profile opt-out from all topics but still a subscriber
                $profileConsentArr['secondary_function'] =
                    $this->formatConsentListArrayForCsv($topicsMapping, [], true);
            }
        } elseif ($consentType == self::CONSENT_TYPE_OPT_OUT) { //Should opt-out from all in selection
            $profileConsentArr['main_function'] =
                $this->formatConsentListArrayForCsv($topicsMapping, [], true);
        }
        return $profileConsentArr;
    }

    /**
     * @param array $topicsMapping
     * @param array $profileTopicArr
     * @param bool $reverse
     *
     * @return array
     */
    public function formatConsentListArrayForCsv(array $topicsMapping, array $profileTopicArr, $reverse = false)
    {
        $formattedConsentArr = [];
        foreach ($topicsMapping as $topicDisc => $listDis) {
            if (isset($profileTopicArr[$topicDisc])) {
                $formattedConsentArr[$topicDisc] = ($reverse) ? '' : 1;
            } else {
                $formattedConsentArr[$topicDisc] = ($reverse) ? 1 : '';
            }
        }
        return (strlen(implode($formattedConsentArr)) == 0) ? [] : $formattedConsentArr;
    }

    /**
     * @param StoreInterface $store
     * @param string $file
     * @param string $consentType
     * @param array $headers
     *
     * @return string
     *
     * @throws FileSystemException
     */
    private function createFileWithHeaders(StoreInterface $store, string $file, string $consentType, array $headers)
    {
        if (empty($file)) {
            $file = strtolower($store->getCode() . '_subscriber_' .
                $consentType . '_' . date('d_m_Y_His') . '.csv');
            $this->apsisFileHelper->outputCSV($file, $headers);
        }
        return $file;
    }

    /**
     * @param array $mappings
     * @param MagentoSubscriber $subscriber
     * @param array $consentLisArr
     *
     * @return array
     */
    private function getSubscriberDataForCsvRow(array $mappings, MagentoSubscriber $subscriber, array $consentLisArr)
    {
        return $this->subscriberDataFactory->create()
            ->setModelData($mappings, $subscriber, $this->apsisCoreHelper)
            ->setConsentListData($consentLisArr)
            ->toCSVArray();
    }

    /**
     * @param array $jsonMappings
     * @param array $mappings
     * @param array $attributesArrWithVersionId
     * @param array $topicsMapping
     * @param string $consentType
     *
     * @return array
     */
    private function createMapping(
        array $jsonMappings,
        array $mappings,
        array $attributesArrWithVersionId,
        array $topicsMapping,
        string $consentType
    ) {
        if (empty($jsonMappings)) {
            $jsonMappings = $this->apsisConfigHelper->getJsonMappingData(
                $this->keySpaceDiscriminator,
                $mappings,
                $attributesArrWithVersionId,
                $topicsMapping,
                $consentType
            );
        }
        return $jsonMappings;
    }

    /**
     * @param string $file
     * @param StoreInterface $store
     * @param array $subscribersToUpdate
     * @param array $jsonMappings
     * @param string $consentType
     * @param string $topics
     *
     */
    private function registerBatchItem(
        string $file,
        StoreInterface $store,
        array $subscribersToUpdate,
        array $jsonMappings,
        string $consentType = '',
        string $topics = ''
    ) {
        try {
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

            if ($consentType == self::CONSENT_TYPE_OPT_IN) {
                $this->profileResource->updateSubscribersSubscription(
                    $subscribersToUpdate,
                    $store->getId(),
                    $this->apsisCoreHelper,
                    $topics
                );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }
    }
}
