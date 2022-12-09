<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;

/**
 * Class Abandoned
 *
 * @method int getQuoteId()
 * @method $this setQuoteId(int $value)
 * @method string getCartData()
 * @method $this setCartData(string $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method int getProfileId()
 * @method $this setProfileId(int $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 * @method int getSubscriberId()
 * @method $this setSubscriberId(int $value)
 * @method string getCustomerEmail()
 * @method $this setCustomerEmail(string $value)
 * @method string getToken()
 * @method $this setToken(string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 */
class Abandoned extends AbstractModel
{
    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ExpressionFactory
     */
    private $expressionFactory;

    /**
     * Abandoned constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param ApsisLogHelper $apsisLogHelper
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        ApsisLogHelper $apsisLogHelper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->apsisLogHelper = $apsisLogHelper;
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
     * Constructor.
     */
    public function _construct()
    {
        $this->_init(AbandonedResource::class);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        if ($this->isDeleted()) {
            //Log it
            $info = [
                'Message' => 'Confirmed delete.',
                'Entity Id' => $this->getId(),
                'Profile Table Id' => $this->getProfileId(),
                'Store Id' => $this->getStoreId()
            ];
            $this->apsisLogHelper->debug(__METHOD__, $info);
        }

        return parent::afterDelete();
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($this->dateTime->formatDate(true));
        }
        $this->setToken(
            $this->expressionFactory->create(
                ["expression" => "(SELECT UUID())"]
            )
        )->setCartData($this->apsisLogHelper->serialize($this->getCartData()));
        return $this;
    }
}
