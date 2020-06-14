<?php

namespace Apsis\One\Model;

use Apsis\One\Helper\Log as ApsisLogHelper;
use Apsis\One\Model\Sql\ExpressionFactory;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

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
