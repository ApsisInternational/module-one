<?php

namespace Apsis\One\Model;

use Apsis\One\Helper\Config as ApsisConfigHelper;
use DateInterval;
use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Apsis\One\Model\Cart\ContentFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Apsis\One\Model\ResourceModel\Event as EventResource;

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
     * @var ContentFactory
     */
    private $cartContentFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var AbandonedResource
     */
    private $abandonedResource;

    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var AbandonedCollectionFactory
     */
    private $abandonedCollectionFactory;

    /**
     * @var DateIntervalFactory
     */
    private $dateIntervalFactory;

    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * Abandoned constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ContentFactory $cartContentFactory
     * @param QuoteCollectionFactory $quoteCollectionFactory
     * @param AbandonedResource $abandonedResource
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     * @param Json $jsonSerializer
     * @param DateIntervalFactory $dateIntervalFactory
     * @param EventResource $eventResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ContentFactory $cartContentFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        AbandonedResource $abandonedResource,
        ApsisCoreHelper $apsisCoreHelper,
        AbandonedCollectionFactory $abandonedCollectionFactory,
        Json $jsonSerializer,
        DateIntervalFactory $dateIntervalFactory,
        EventResource $eventResource,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->eventResource = $eventResource;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->abandonedResource = $abandonedResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->cartContentFactory = $cartContentFactory;
        $this->dateTime = $dateTime;
        $this->jsonSerializer = $jsonSerializer;
        $this->dateIntervalFactory = $dateIntervalFactory;
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
     *
     * @throws LocalizedException
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
                $quoteCollection = $this->getQuoteCollectionByStore($store, $acDelayPeriod);
                if ($quoteCollection && $quoteCollection->getSize()) {
                    $this->processStoreQuoteCollection($quoteCollection);
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

    /**
     * @param StoreInterface $store
     * @param string|int $acDelayPeriod
     *
     * @return Collection|boolean
     */
    private function getQuoteCollectionByStore(StoreInterface $store, $acDelayPeriod)
    {
        try {
            $interval = $this->getInterval($acDelayPeriod);
            $fromTime = new \DateTime('now', new \DateTimezone('UTC'));
            $fromTime->sub($interval);
            $toTime = clone $fromTime;
            $fromTime->sub($this->dateIntervalFactory->create(['interval_spec' => 'PT5M']));
            $updated = [
                'from' => $fromTime->format('Y-m-d H:i:s'),
                'to' => $toTime->format('Y-m-d H:i:s'),
                'date' => true,
            ];

            $quoteCollection = $this->quoteCollectionFactory->create()
                ->addFieldToFilter('is_active', 1)
                ->addFieldToFilter('items_count', ['gt' => 0])
                ->addFieldToFilter('customer_email', ['notnull' => true])
                ->addFieldToFilter('main_table.store_id', $store->getId())
                ->addFieldToFilter('main_table.updated_at', $updated);
            return $quoteCollection;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string|int $acDelayPeriod
     *
     * @return DateInterval
     */
    private function getInterval($acDelayPeriod)
    {
        $interval = $this->dateIntervalFactory->create(
            ['interval_spec' => sprintf('PT%sM', $acDelayPeriod)]
        );
        return $interval;
    }

    /**
     * @param Collection $quoteCollection
     *
     * @throws LocalizedException
     */
    private function processStoreQuoteCollection(Collection $quoteCollection)
    {
        $abandonedCarts = [];
        $events = [];
        foreach ($quoteCollection as $quote) {
            $cartData = $this->cartContentFactory->create()
                ->getCartData($quote);

            if (! empty($cartData)) {
                $token = $this->apsisCoreHelper->getRandomString();
                $createdAt = $this->dateTime->formatDate(true);
                $abandonedCarts[] = [
                    'quote_id' => $quote->getId(),
                    'cart_data' => $this->jsonSerializer->serialize($cartData),
                    'store_id' => $quote->getStoreId(),
                    'customer_id' => $quote->getCustomerId(),
                    'customer_email' => $quote->getCustomerEmail(),
                    'token' => $token,
                    'created_at' => $createdAt
                ];

                $events[] = [
                    'event_type' => Event::EVENT_TYPE_AC,
                    'event_data' => $this->jsonSerializer->serialize(
                        [
                           'quote_id' => $quote->getId(),
                           'token' => $token
                        ]
                    ),
                    'subscriber_id' => '',
                    'customer_id' => $quote->getCustomerId(),
                    'store_id' => $quote->getStoreId(),
                    'email' => $quote->getCustomerEmail(),
                    'status' => Event::EVENT_STATUS_PENDING,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        if (! empty($abandonedCarts)) {
            $this->abandonedResource->insertAbandonedCarts($abandonedCarts);
        }

        if (! empty($events)) {
            $this->eventResource->insertEvents($events);
        }
    }
}
