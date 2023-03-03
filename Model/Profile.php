<?php

namespace Apsis\One\Model;

use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Throwable;

/**
 * Class Profile
 *
 * @method string getProfileUuid()
 * @method $this setProfileUuid(string $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method int getSubscriberId()
 * @method $this setSubscriberId(int $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method string getEmail()
 * @method $this setEmail(string $value)
 * @method int getIsSubscriber()
 * @method $this setIsSubscriber(int $value)
 * @method int getIsCustomer()
 * @method $this setIsCustomer(int $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 */
class Profile extends AbstractModel
{
    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @var ExpressionFactory
     */
    private ExpressionFactory $expressionFactory;

    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * Subscriber constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ProfileResource $profileResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        ApsisCoreHelper $apsisCoreHelper,
        ProfileResource $profileResource,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->profileResource = $profileResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->expressionFactory = $expressionFactory;
        $this->dateTime = $dateTime;
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @inheritdoc
     */
    public function _construct()
    {
        $this->_init(ProfileResource::class);
    }

    /**
     * @return Profile
     */
    public function afterDelete()
    {
        try {
            if ($this->isDeleted()) {
                //@todo send profile update

                //Log it
                $info = [
                    'Message' => 'Profile removed from integration table.',
                    'Entity Id' => $this->getId(),
                    'Store Id' => $this->getStoreId(),
                    'Profile Id' => $this->getProfileUuid()
                ];
                $this->apsisCoreHelper->debug(__METHOD__, $info);
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        return parent::afterDelete();
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        $store = $this->apsisCoreHelper->getStore($this->getStoreId());

        // Always set update at date
        $this->setUpdatedAt($this->dateTime->formatDate(true));

        // Aggregate profile data column
        if ($this->getCustomerId()) {
            $expressionString = $this->profileResource
                ->buildProfileDataQueryForCustomer($store, $this->apsisCoreHelper, $this->getCustomerId());
        } elseif ($this->getSubscriberId()) {
            $expressionString = $this->profileResource
                ->buildProfileDataQueryForSubscriber($store, $this->apsisCoreHelper, $this->getSubscriberId());
        }
        if (! empty($expressionString)) {
            $this->setProfileData($this->expressionFactory->create(["expression" => $expressionString]));
        }

        // Assign profile a UUID if object is new
        if ($this->isObjectNew()) {
            $this->setProfileUuid(
                $this->expressionFactory->create(
                    ["expression" => "(SELECT UUID())"]
                )
            );
        }

        return $this;
    }
}
