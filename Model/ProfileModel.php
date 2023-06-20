<?php

namespace Apsis\One\Model;

use Apsis\One\Service\BaseService;
use Apsis\One\Service\QueueService;
use Apsis\One\Service\Sub\SubQueueService;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Apsis\One\Model\ResourceModel\ProfileResource;
use Apsis\One\Model\ResourceModel\Webhook\WebhookCollectionFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Throwable;

/**
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method int|null getSubscriberId()
 * @method $this setSubscriberId(int|null $value)
 * @method int|null getCustomerId()
 * @method $this setCustomerId(int|null $value)
 * @method int|null getGroupId()
 * @method $this setGroupId(int|null $value)
 * @method string getEmail()
 * @method $this setEmail(string $value)
 * @method int getIsSubscriber()
 * @method $this setIsSubscriber(int $value)
 * @method int|null getSubscriberStatus()
 * @method $this setSubscriberStatus(int|null $value)
 * @method int getIsCustomer()
 * @method $this setIsCustomer(int $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 * @method string getProfileData()
 * @method $this setProfileData(string $value)
 */
class ProfileModel extends AbstractModel
{
    const RESOURCE_MODEL = ProfileResource::class;

    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * @var SubQueueService
     */
    private SubQueueService $subQueueService;

    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var WebhookCollectionFactory
     */
    public WebhookCollectionFactory $webhookCollectionFactory;

    /**
     * @var string
     */
    private string $oldProfileJson;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param ProfileResource $profileResource
     * @param SubQueueService $subQueueService
     * @param WebhookCollectionFactory $webhookCollectionFactory
     * @param BaseService $baseService
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        ProfileResource $profileResource,
        SubQueueService $subQueueService,
        WebhookCollectionFactory $webhookCollectionFactory,
        BaseService $baseService,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $dateTime,
            $expressionFactory,
            $resource,
            $resourceCollection,
            $data
        );
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->subQueueService = $subQueueService;
        $this->profileResource = $profileResource;
        $this->baseService = $baseService;
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ProfileResource::class);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete(): static
    {
        try {
            if ($this->isDeleted()) {
                //Log it
                $info = [
                    'Message' => 'Profile removed from integration table.',
                    'Profile Id' => $this->getId(),
                    'Store Id' => $this->getStoreId()
                ];
                $this->baseService->debug(__METHOD__, $info);
                $this->subQueueService->registerItem($this, $this->baseService, QueueModel::RECORD_DELETED);
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }

        return parent::afterDelete();
    }

    /**
     * @inheirtDoc
     */
    public function beforeSave(): static
    {
        try {
            if (! $this->isObjectNew()) {
                $this->oldProfileJson = (string) $this->getProfileData();
            }
            $store = $this->baseService->getStore($this->getStoreId());

            // Aggregate profile data column
            if ($this->getCustomerId()) {
                $expressionString = $this->profileResource
                    ->buildProfileDataQueryForCustomer($store, $this->baseService, $this->getCustomerId());
            } elseif ($this->getSubscriberId()) {
                $expressionString = $this->profileResource
                    ->buildProfileDataQueryForSubscriber($store, $this->baseService, $this->getSubscriberId());
            }
            if (! empty($expressionString)) {
                $this->setProfileData($this->getExpressionModel($expressionString));
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }

        return parent::beforeSave();
    }

    /**
     * @inheirtDoc
     */
    public function afterSave(): static
    {
        parent::afterSave();

        try {
            if ($this->isObjectNew()) {
                $this->subQueueService->registerItem($this, $this->baseService, QueueModel::RECORD_CREATED);
            } else {
                $webhooks = $this->webhookCollectionFactory
                    ->create()
                    ->getCollectionForStoreByType(
                        $this->getStoreId(),
                        QueueService::QUEUE_TO_WEBHOOK_MAP[QueueModel::RECORD_UPDATED],
                        $this->baseService
                    );
                if (is_int($webhooks) || ! $webhooks->getSize()) {
                    return $this;
                }

                // reload to fetch actual profile data instead of sql expression.
                $this->profileResource->load($this, $this->getId());

                $isDataChanged = $this->computeDataChangeByFilter(
                    $this->oldProfileJson,
                    (string) $this->getProfileData(),
                    (string) $webhooks->getFirstItem()->getFields()
                );
                if ($isDataChanged === true) {
                    $this->subQueueService->registerItem($this, $this->baseService, QueueModel::RECORD_UPDATED, false);
                }
            }
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }

        return $this;
    }

    /**
     * @param string $oldJsonData
     * @param string $newJsonData
     * @param string $listFilter
     *
     * @return bool|null
     */
    private function computeDataChangeByFilter(string $oldJsonData, string $newJsonData, string $listFilter): ?bool
    {
        try {
            if ($listFilter === '*') {
                return true;
            }

            if (empty($oldJsonData) || empty($newJsonData) || empty($listFilter) || $oldJsonData === $newJsonData ||
                $listFilter === 'profile_id'
            ) {
                return false;
            }

            $oldJsonData = json_decode($oldJsonData, true);
            $newJsonData = json_decode($newJsonData, true);
            $filterArr = explode(',', $listFilter);
            if (empty($oldJsonData) || empty($newJsonData) || empty($filterArr)) {
                return false;
            }

            foreach ($filterArr as $filter) {
                if (! array_key_exists($filter, $oldJsonData) || ! array_key_exists($filter, $newJsonData)) {
                    return false;
                }

                if ($newJsonData[$filter] !== $oldJsonData[$filter]) {
                    return true;
                }
            }

            return false;
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
            return null;
        }
    }
}
