<?php

namespace Apsis\One\Model;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Apsis\One\Model\Sql\ExpressionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;
use Apsis\One\Model\Abandoned\AbandonedSubFactory;
use Apsis\One\Model\Abandoned\AbandonedSub;
use Magento\Store\Model\ScopeInterface;

class Abandoned extends AbstractModel
{
    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var AbandonedCollectionFactory
     */
    private $abandonedCollectionFactory;

    /**
     * @var AbandonedSubFactory
     */
    private $abandonedSubFactory;

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
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     * @param AbandonedSubFactory $abandonedSubFactory
     * @param ExpressionFactory $expressionFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ApsisCoreHelper $apsisCoreHelper,
        AbandonedCollectionFactory $abandonedCollectionFactory,
        AbandonedSubFactory $abandonedSubFactory,
        ExpressionFactory $expressionFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->expressionFactory = $expressionFactory;
        $this->abandonedSubFactory = $abandonedSubFactory;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
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
        )->setCartData($this->apsisCoreHelper->serialize($this->getCartData()));

        return $this;
    }

    /**
     * Find AC and aggregate data
     */
    public function processAbandonedCarts()
    {
        $stores = $this->apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $isEnabled = $this->apsisCoreHelper->isEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
            $acDelayPeriod = $this->apsisCoreHelper
                ->getStoreConfig($store, ApsisConfigHelper::CONFIG_APSIS_ONE_ABANDONED_CARTS_SEND_AFTER);

            if ($isEnabled && $acDelayPeriod) {
                /** @var AbandonedSub $abandonedSub */
                $abandonedSub = $this->abandonedSubFactory->create();
                $quoteCollection = $abandonedSub->getQuoteCollectionByStore($store, $acDelayPeriod);
                if ($quoteCollection && $quoteCollection->getSize()) {
                    $abandonedSub->aggregateCartDataFromStoreCollection($quoteCollection, $this->apsisCoreHelper);
                }
            }
        }
    }

    /**
     * @param string $token
     * @return bool|DataObject
     */
    public function getCart(string $token)
    {
        return $this->abandonedCollectionFactory->create()
            ->loadByToken($token);
    }
}
