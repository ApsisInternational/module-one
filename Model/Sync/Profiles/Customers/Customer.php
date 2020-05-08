<?php

namespace Apsis\One\Model\Sync\Profiles\Customers;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Magento\Customer\Model\Customer as MagentoCustomer;
use Magento\Customer\Model\GroupFactory;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group as GroupResource;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\Collection as ReviewCollection;

class Customer
{
    /**
     * @var array
     */
    private $customerData = [];

    /**
     * @var MagentoCustomer
     */
    private $customer;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ReviewCollectionFactory
     */
    private $reviewCollectionFactory;

    /**
     * @var ReviewCollection
     */
    private $reviewCollection;

    /**
     * @var GroupFactory
     */
    private $groupFactory;

    /**
     * @var GroupResource
     */
    private $groupResource;

    /**
     * Customer constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param GroupFactory $groupFactory
     * @param GroupResource $groupResource
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        ReviewCollectionFactory $reviewCollectionFactory,
        GroupFactory $groupFactory,
        GroupResource $groupResource
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->groupFactory = $groupFactory;
        $this->groupResource = $groupResource;
    }

    /**
     * @param array $mappingHash
     * @param MagentoCustomer $customer
     *
     * @return $this
     */
    public function setCustomerData(array $mappingHash, MagentoCustomer $customer)
    {
        $this->customer = $customer;
        $this->setReviewCollection();
        foreach ($mappingHash as $key) {
            $function = 'get';
            $exploded = explode('_', $key);
            foreach ($exploded as $one) {
                $function .= ucfirst($one);
            }
            $this->customerData[$key] = call_user_func(['self', $function]);
        }
        return $this;
    }

    /**
     * Customer reviews.
     *
     * @return $this
     */
    private function setReviewCollection()
    {
        $collection = $this->reviewCollectionFactory->create()
            ->addCustomerFilter($this->customer->getId())
            ->setOrder('review_id', 'DESC');

        $this->reviewCollection = $collection;

        return $this;
    }

    /**
     * Contact data array.
     *
     * @return array
     */
    public function toCSVArray()
    {
        return array_values($this->customerData);
    }

    /**
     * @return string
     */
    private function getIntegrationUid()
    {
        return (string) $this->customer->getIntegrationUid();
    }

    /**
     * @return string
     */
    private function getEmail()
    {
        return (string) $this->customer->getEmail();
    }

    /**
     * @return int
     */
    private function getStoreId()
    {
        return (int) $this->customer->getStoreId();
    }

    /**
     * @return string
     */
    private function getStoreName()
    {
        return (string) $this->customer->getStoreName();
    }

    /**
     * @return int
     */
    private function getWebsiteId()
    {
        return (int) $this->customer->getWebsiteId();
    }

    /**
     * @return string
     */
    private function getWebsiteName()
    {
        return (string) $this->customer->getWebsiteName();
    }

    /**
     * @return string
     */
    private function getTitle()
    {
        return (string) $this->customer->getPrefix();
    }

    /**
     * @return int
     */
    private function getCustomerId()
    {
        return (int) $this->customer->getId();
    }

    /**
     * @return string
     */
    private function getFirstName()
    {
        return (string) $this->customer->getFirstname();
    }

    /**
     * @return string
     */
    private function getLastName()
    {
        return (string) $this->customer->getLastname();
    }

    /**
     * @return string
     */
    private function getDob()
    {
        return (string) ($this->customer->getDob()) ?
            $this->apsisCoreHelper->formatDateForPlatformCompatibility($this->customer->getDob(), 'Y-m-d') : '';
    }

    /**
     * @return string
     */
    private function getGender()
    {
        $genderId = $this->customer->getGender();
        if (is_numeric($genderId)) {
            $gender = $this->customer->getAttribute('gender')
                ->getSource()->getOptionText($genderId);

            return (string) $gender;
        }

        return '';
    }

    /**
     * @return int|string
     */
    private function getCreatedAt()
    {
        return ($this->customer->getCreatedAt()) ?
            (int) $this->apsisCoreHelper->formatDateForPlatformCompatibility($this->customer->getCreatedAt()) : '';
    }

    /**
     * Get customer last logged in date.
     *
     * @return int|string
     */
    private function getLastLoggedDate()
    {
        return ($this->customer->getLastLoggedDate()) ?
            (int) $this->apsisCoreHelper->formatDateForPlatformCompatibility($this->customer->getLastLoggedDate()) : '';
    }

    /**
     * @return string
     */
    private function getCustomerGroup()
    {
        $groupId = $this->customer->getGroupId();
        /** @var Group $groupModel */
        $groupModel = $this->groupFactory->create();
        $this->groupResource->load($groupModel, $groupId);
        if ($groupModel) {
            return (string) $groupModel->getCode();
        }

        return '';
    }

    /**
     * @return int
     */
    private function getReviewCount()
    {
        return (int) count($this->reviewCollection);
    }

    /**
     * @return int|string
     */
    private function getLastReviewDate()
    {
        if ($this->reviewCollection->getSize()) {
            $this->reviewCollection->getSelect()->limit(1);
            $createdAt = $this->reviewCollection
                ->getFirstItem()
                ->getCreatedAt();
            return ($createdAt) ? (int) $this->apsisCoreHelper->formatDateForPlatformCompatibility($createdAt) : '';
        }

        return 0;
    }

    /**
     * @return string
     */
    private function getBillingAddress1()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
            ! $this->customer->getBillingStreet()
        ) {
            return (string) $this->getStreet($this->customer->getShippingStreet(), 1);
        }

        return (string) $this->getStreet($this->customer->getBillingStreet(), 1);
    }

    /**
     * @return string
     */
    private function getBillingAddress2()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
            ! $this->customer->getBillingStreet()
        ) {
            return (string) $this->getStreet($this->customer->getShippingStreet(), 2);
        }

        return (string) $this->getStreet($this->customer->getBillingStreet(), 2);
    }

    /**
     * @return string
     */
    private function getBillingCity()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getBillingCity()
        ) {
            return (string) $this->customer->getShippingCity();
        }

        return (string) $this->customer->getBillingCity();
    }

    /**
     * @return string
     */
    private function getBillingCountry()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getBillingCountryCode()
        ) {
            return (string) $this->customer->getShippingCountryCode();
        }

        return (string) $this->customer->getBillingCountryCode();
    }

    /**
     * @return string
     */
    private function getBillingState()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getBillingRegion()
        ) {
            return (string) $this->customer->getShippingRegion();
        }

        return (string) $this->customer->getBillingRegion();
    }

    /**
     * @return string
     */
    private function getBillingPostcode()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
            $this->customer->getBillingPostcode()
        ) {
            return (string) $this->customer->getShippingPostcode();
        }

        return (string) $this->customer->getBillingPostcode();
    }

    /**
     * @return string
     */
    private function getBillingTelephone()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getBillingTelephone()
        ) {
            return (string) $this->customer->getShippingTelephone();
        }

        return (string) $this->customer->getBillingTelephone();
    }

    /**
     * @return string
     */
    private function getBillingCompany()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getBillingCompany()
        ) {
            return (string) $this->customer->getShippingCompany();
        }

        return (string) $this->customer->getBillingCompany();
    }

    /**
     * @return string
     */
    private function getDeliveryAddress1()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingStreet()
        ) {
            return (string) $this->getStreet($this->customer->getBillingStreet(), 1);
        }

        return (string) $this->getStreet($this->customer->getShippingStreet(), 1);
    }

    /**
     * @return string
     */
    private function getDeliveryAddress2()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingStreet()
        ) {
            return (string) $this->getStreet($this->customer->getBillingStreet(), 2);
        }

        return (string) $this->getStreet($this->customer->getShippingStreet(), 2);
    }

    /**
     * @return string
     */
    private function getDeliveryCity()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingCity()
        ) {
            return (string) $this->customer->getBillingCity();
        }

        return (string) $this->customer->getShippingCity();
    }

    /**
     * @return string
     */
    private function getDeliveryCountry()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingCountryCode()
        ) {
            return (string) $this->customer->getBillingCountryCode();
        }

        return (string) $this->customer->getShippingCountryCode();
    }

    /**
     * @return string
     */
    private function getDeliveryState()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingRegion()
        ) {
            return (string) $this->customer->getBillingState();
        }

        return (string) $this->customer->getShippingRegion();
    }

    /**
     * @return string
     */
    private function getDeliveryPostcode()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingPostcode()
        ) {
            return (string) $this->customer->getBillingPostcode();
        }

        return (string) $this->customer->getShippingPostcode();
    }

    /**
     * @return string
     */
    private function getDeliveryTelephone()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingTelephone()
        ) {
            return (string) $this->customer->getBillingTelephone();
        }

        return (string) $this->customer->getShippingTelephone();
    }

    /**
     * @return string
     */
    private function getDeliveryCompany()
    {
        if ($this->customer->getDefaultBilling() === $this->customer->getDefaultShipping() &&
        ! $this->customer->getShippingCompany()
        ) {
            return (string) $this->customer->getBillingCompany();
        }

        return (string) $this->customer->getShippingCompany();
    }

    /**
     * @param string $street
     * @param int $line
     *
     * @return string
     */
    private function getStreet($street, $line)
    {
        $street = explode("\n", $street);
        if (isset($street[$line - 1])) {
            return (string) $street[$line - 1];
        }

        return '';
    }

    /**
     * @return int|string
     */
    private function getLastOrderDate()
    {
        return ($this->customer->getLastOrderDate()) ?
            (int) $this->apsisCoreHelper->formatDateForPlatformCompatibility($this->customer->getLastOrderDate()) : '';
    }

    /**
     * @return int
     */
    private function getNumberOfOrders()
    {
        return (int) $this->customer->getNumberOfOrders();
    }

    /**
     * @return float
     */
    private function getAverageOrderValue()
    {
        return (float) $this->apsisCoreHelper->round($this->customer->getAverageOrderValue());
    }

    /**
     * @return float
     */
    private function getTotalSpend()
    {
        return (float) $this->apsisCoreHelper->round($this->customer->getTotalSpend());
    }
}
