<?php

namespace Apsis\One\Observer\Customer\Review;

use Apsis\One\Model\Profile;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Review\Model\Review;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Model\EventFactory;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\Event;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Customer\Model\Customer;
use Apsis\One\Helper\Config as ApsisConfigHelper;

class Product implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * @var EventResource
     */
    private $eventResource;

    /**
     * Product constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventFactory $eventFactory
     * @param EventResource $eventResource
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        EventFactory $eventFactory,
        EventResource $eventResource
    ) {
        $this->eventFactory = $eventFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->eventResource = $eventResource;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        /** @var Review $reviewObject */
        $reviewObject = $observer->getEvent()->getDataObject();
        /** @var MagentoProduct $product */
        $product = $this->apsisCoreHelper->getProductById($reviewObject->getEntityPkValue());
        /** @var Customer $customer */
        $customer = $this->apsisCoreHelper->getCustomer($reviewObject->getCustomerId());

        if ($customer && $product && $this->isOkToProceed()) {
            $data = (array) $this->getDataArr($reviewObject, $product);
            $eventModel = $this->eventFactory->create()
                ->setEventType(Event::EVENT_TYPE_CUSTOMER_LEFT_PRODUCT_REVIEW)
                ->setEventData($this->apsisCoreHelper->serialize($data))
                ->setCustomerId($reviewObject->getCustomerId())
                ->setStoreId($this->apsisCoreHelper->getStore()->getId())
                ->setEmail($customer->getEmail())
                ->setStatus(Profile::SYNC_STATUS_PENDING);

            try {
                $this->eventResource->save($eventModel);
            } catch (Exception $e) {
                $this->apsisCoreHelper->logMessage(__NAMESPACE__, __METHOD__, $e->getMessage());
            }
        }
        return $this;
    }

    /**
     * @return bool
     */
    private function isOkToProceed()
    {
        $store = $this->apsisCoreHelper->getStore();
        $account = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_ACCOUNTS_OAUTH_ENABLED
        );

        $event = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_CUSTOMER_REVIEW
        );

        $sync = (boolean) $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_CUSTOMER_ENABLED
        );

        return ($account && $event && $sync) ? true : false;
    }

    /**
     * @param Review $reviewObject
     * @param MagentoProduct $product
     *
     * @return array
     */
    private function getDataArr(Review $reviewObject, MagentoProduct $product)
    {
        $data = [
            'review_id' => (int)$reviewObject->getReviewId(),
            'customer_id' => (int)$reviewObject->getCustomerId(),
            'created_at' => (string)$this->apsisCoreHelper
                ->formatDateForPlatformCompatibility($reviewObject->getCreatedAt()),
            'website_name' => (string)$this->apsisCoreHelper
                ->getWebsiteNameFromStoreId(),
            'store_name' => (string)$this->apsisCoreHelper->getStoreNameFromId(),
            'nickname' => (string)$reviewObject->getNickname(),
            'review_title' => (string)$reviewObject->getTitle(),
            'review_detail' => (string)$reviewObject->getDetail(),
            'product_id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'name' => (string)$product->getName(),
            'product_url' => (string)$product->getProductUrl(),
            'product_review_url' => (string)$reviewObject->getReviewUrl(),
            'product_image_url' => (string)$this->apsisCoreHelper->getProductImageUrl($product),
            'catalog_price_amount' => (float)$this->apsisCoreHelper->round($product->getPrice())
        ];
        return $data;
    }
}
