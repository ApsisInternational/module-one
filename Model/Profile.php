<?php

namespace Apsis\One\Model;

use Apsis\One\Model\Sql\ExpressionFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;

class Profile extends AbstractModel
{
    const SYNC_STATUS_PENDING = 0;
    const SYNC_STATUS_SYNCED = 1;
    const SYNC_STATUS_FAILED = 2;
    const SYNC_STATUS_BATCHED = 3;

    const IS_FLAGGED = 1;
    const IS_FLAGGED_NO = 0;

    const DEFAULT_HEADERS = 'integration_uid';

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ExpressionFactory
     */
    private $expressionFactory;

    /**
     * Subscriber constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
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
     * Constructor
     */
    public function _construct()
    {
        $this->_init(ProfileResource::class);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();
        $this->setUpdatedAt($this->dateTime->formatDate(true));

        if ($this->isObjectNew()) {
            $this->setIntegrationUid(
                $this->expressionFactory->create(
                    ["expression" => "(SELECT UUID())"]
                )
            );
        }
        return $this;
    }
}
