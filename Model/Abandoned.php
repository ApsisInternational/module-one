<?php

namespace Apsis\One\Model;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use Magento\Framework\DataObject;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;
use Apsis\One\Model\Abandoned\AbandonedSubFactory;
use Apsis\One\Model\Abandoned\AbandonedSub;

class Abandoned extends AbstractModel
{
    /**
     * @var Json
     */
    private $jsonSerializer;

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
     * Abandoned constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     * @param Json $jsonSerializer
     * @param AbandonedSubFactory $abandonedSubFactory
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
        Json $jsonSerializer,
        AbandonedSubFactory $abandonedSubFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->abandonedSubFactory = $abandonedSubFactory;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->dateTime = $dateTime;
        $this->jsonSerializer = $jsonSerializer;
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
     * @return $this|AbstractModel
     */
    public function beforeSave()
    {
        parent::beforeSave();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($this->dateTime->formatDate(true));
        }

        $this->setToken($this->apsisCoreHelper->getRandomString())
            ->setCartData($this->jsonSerializer->serialize($this->getCartData()));

        return $this;
    }

    /**
     * Find AC and aggregate data
     */
    public function processAbandonedCarts()
    {
        $stores = $this->apsisCoreHelper->getStores();
        foreach ($stores as $store) {
            $isEnabled = $this->apsisCoreHelper
                ->getStoreConfig($store, ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED);
            $acDelayPeriod = $this->apsisCoreHelper
                ->getStoreConfig($store, ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED);

            if ($isEnabled && $acDelayPeriod) {
                /** @var AbandonedSub $abandonedSub */
                $abandonedSub = $this->abandonedSubFactory->create();
                $quoteCollection = $abandonedSub->getQuoteCollectionByStore($store, $acDelayPeriod);
                if ($quoteCollection && $quoteCollection->getSize()) {
                    $abandonedSub->aggregateCartDataFromStoreCollection(
                        $quoteCollection,
                        $this->apsisCoreHelper,
                        $this->jsonSerializer,
                        $this->dateTime->formatDate(true)
                    );
                }
            }
        }
    }

    /**
     * @param int $quoteId
     * @param string $token
     *
     * @return array|string
     */
    public function getCartJsonData(int $quoteId, string $token)
    {
        $cart = $this->loadByQuoteId($quoteId);
        return (! empty($cart) && $cart->getToken() === $token) ? (string) $cart->getCartData() : [];
    }

    /**
     * @param int $quoteId
     *
     * @return bool|DataObject
     */
    private function loadByQuoteId(int $quoteId)
    {
        return $this->abandonedCollectionFactory->create()
            ->loadByQuoteId($quoteId);
    }
}
