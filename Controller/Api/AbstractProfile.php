<?php

namespace Apsis\One\Controller\Api;

use Apsis\One\Controller\Api\Profiles\Index as ProfilesIndex;
use Apsis\One\Controller\Api\Consents\Index as ConsentsIndex;
use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Apsis\One\Model\Service\Date as ApsisDateHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Registry;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Customer\Model\CustomerFactory;
use Throwable;

abstract class AbstractProfile extends AbstractApi
{
    const SUBSCRIBER_STATUS_MAP = [
        Subscriber::STATUS_SUBSCRIBED => 'Subscribed',
        Subscriber::STATUS_NOT_ACTIVE => 'Not Active',
        Subscriber::STATUS_UNSUBSCRIBED => 'Unsubscribed',
        Subscriber::STATUS_UNCONFIRMED => 'Unconfirmed',
    ];

    /**
     * @var GroupCollectionFactory
     */
    protected GroupCollectionFactory $groupCollectionFactory;

    /**
     * @var ProfileCollectionFactory
     */
    protected ProfileCollectionFactory $profileCollectionFactory;

    /**
     * @var EavConfig
     */
    protected EavConfig $eavConfig;

    /**
     * @var ApsisDateHelper
     */
    protected ApsisDateHelper $apsisDateHelper;

    /**
     * @var ProfileResource
     */
    protected ProfileResource $profileResource;

    /**
     * @var SubscriberFactory
     */
    protected SubscriberFactory $subscriberFactory;

    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param CustomerFactory $customerFactory
     * @param Escaper $escaper
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     * @param EavConfig $eavConfig
     * @param ApsisDateHelper $apsisDateHelper
     * @param ProfileResource $profileResource
     * @param SubscriberFactory $subscriberFactory
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        CustomerFactory $customerFactory,
        Escaper $escaper,
        GroupCollectionFactory $groupCollectionFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        EavConfig $eavConfig,
        ApsisDateHelper $apsisDateHelper,
        ProfileResource $profileResource,
        SubscriberFactory $subscriberFactory,
        Registry $registry
    ) {
        $this->subscriberFactory = $subscriberFactory;
        $this->profileResource = $profileResource;
        $this->apsisDateHelper = $apsisDateHelper;
        $this->eavConfig = $eavConfig;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->registry = $registry;
        parent::__construct($context, $apsisCoreHelper, $customerFactory, $escaper);
    }

    /**
     * @param int $id
     *
     * @return bool|int
     */
    protected function doesGroupIdExist(int $id)
    {
        try {
            $collection = $this->groupCollectionFactory->create()
                ->addFieldToFilter('main_table.customer_group_id', $id);
            return $collection->getSize() ? true : 404;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getGroupRecordsCount()
    {
        try {
            $collection = $this->getProfileCollectionByGroupId(false);
            return is_int($collection) ? $collection : ['count' => $collection->getSize()];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getGroupRecords()
    {
        try {
            $collection = $this->getProfileCollectionByGroupId(true);
            if (is_int($collection)) {
                return $collection;
            }

            $records = [];
            /** @var Profile $profile */
            foreach ($collection as $profile) {
                $records[] = [ProfilesIndex::ENTITY_NAME => $profile->getId()];
            }
            return $records;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param bool $addPagination
     *
     * @return int|AbstractCollection
     */
    private function getProfileCollectionByGroupId(bool $addPagination)
    {
        try {
            $collection = $this->profileCollectionFactory->create()
                ->addFieldToFilter('group_id', $this->taskId);
            $collection = $this->addStoreFilterOnCollection($collection);
            return $addPagination ? $this->setPaginationOnCollection($collection, 'id') : $collection;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getConsentsCount()
    {
        try {
            $collection = $this->getProfileCollectionForConsents(false);
            return is_int($collection) ? $collection : ['count' => $collection->getSize()];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getConsents()
    {
        try {
            $collection = $this->getProfileCollectionForConsents(true);
            if (is_int($collection)) {
                return $collection;
            }

            $consents = [];
            /** @var Profile $profile */
            foreach ($collection as $profile) {
                $consented = $this->getConsentedStatus($profile);
                if ($consented === null) {
                    continue;
                }

                $consents[] = [
                    'consent_base_id' => ConsentsIndex::CONSENT_BASE_ID,
                    'record_id' => $profile->getId(),
                    'has_consented' => $consented
                ];
            }
            return $consents;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return bool|int
     */
    protected function updateConsent()
    {
        try {
            if (! is_numeric($this->requestBody['record_id']) ||
                $this->requestBody['consent_base_id'] !== ConsentsIndex::CONSENT_BASE_ID ||
                $this->requestBody['has_consented'] !== false
            ) {
                return 400;
            }

            $collection = $this->profileCollectionFactory->create()
                ->addFieldToFilter('id', (int) $this->requestBody['record_id']);
            if (! $collection->getSize()) {
                return 404;
            }

            /** @var Profile $profile */
            $profile = $collection->getFirstItem();
            if (! $profile->getSubscriberId()) {
                return 404;
            }

            $subscriber = $this->subscriberFactory->create()->load($profile->getSubscriberId());
            if (! $subscriber->getId()) {
                return 404;
            }

            $this->registry->register($subscriber->getEmail() . '_subscription', true, true);
            $subscriber->unsubscribe();
            $profile->setSubscriberStatus(Subscriber::STATUS_UNSUBSCRIBED);
            $this->profileResource->save($profile);

            return true;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param Profile $profile
     *
     * @return bool|null
     */
    private function getConsentedStatus(Profile $profile)
    {
        $consented = null;
        if ($profile->getSubscriberStatus() == Subscriber::STATUS_SUBSCRIBED) {
            $consented = true;
        }
        if ($profile->getSubscriberStatus() == Subscriber::STATUS_UNSUBSCRIBED) {
            $consented = false;
        }
        return $consented;
    }

    /**
     * @param bool $addPagination
     *
     * @return int|AbstractCollection
     */
    private function getProfileCollectionForConsents(bool $addPagination)
    {
        try {
            $collection = $this->profileCollectionFactory->create()
                ->addFieldToFilter('is_subscriber', 1)
                ->addFieldToFilter(
                    'subscriber_status',
                    ['in' => [Subscriber::STATUS_SUBSCRIBED, Subscriber::STATUS_UNSUBSCRIBED]]
                )
                ->addFieldToFilter('subscriber_id', ['notnull' => true]);
            $collection = $this->addStoreFilterOnCollection($collection);
            return $addPagination ? $this->setPaginationOnCollection($collection, 'id') : $collection;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getProfilesCount()
    {
        try {
            $collection = $this->getProfileCollectionForRecords(false);
            return is_int($collection) ? $collection : ['count' => $collection->getSize()];
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getProfiles()
    {
        try {
            $collection = $this->getProfileCollectionForRecords(true);
            if (is_int($collection)) {
                return $collection;
            }

            // If ids are provided than filter by it on collection
            $ids = ! empty($this->queryParams['ids']) ? explode(',', (string) $this->queryParams['ids']) : null;
            if (is_array($ids) && ! empty($ids)) {
                $collection->addFieldToFilter('id', ['in' => $ids]);
            }

            // Only return fields on record if sent otherwise send all fields
            $fields = ! empty($this->queryParams['fields']) ?
                explode(',', (string) $this->queryParams['fields']) : ['*'];

            $records = [];
            foreach ($collection as $profile) {
                $profileDataArr = $this->getProfileDataArr($profile, $fields);
                if (! empty($profileDataArr)) {
                    $records[] = $profileDataArr;
                }
            }
            return $records;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param Profile $profile
     * @param array $requestedFields
     *
     * @return array
     */
    private function getProfileDataArr(Profile $profile, array $requestedFields): array
    {
        try {
            $dataArr = [];
            $profileData = array_merge(
                json_decode($profile->getProfileData(), true),
                ['profile_id' => $profile->getId()]
            );

            $customerAttr = $this->getCustomerAttributes();
            $schemaFields = in_array('*', $requestedFields) ?
                array_merge(ProfilesIndex::SCHEMA, $customerAttr) :
                array_intersect_key(array_merge(ProfilesIndex::SCHEMA, $customerAttr), array_flip($requestedFields));

            foreach ($schemaFields as $field) {
                if (isset($profileData[$field['code_name']])) {
                    $dataArr[$field['code_name']] = $this->getSchemaFieldValue($field, $profileData);
                } elseif ($profile->getCustomerId() && isset($customerAttr[$field['code_name']])) {
                    $dataArr[$field['code_name']] = $this->getCustomerAttributeValue($profile, $field['code_name']);
                } else {
                    $dataArr[$field['code_name']] = null;
                }
            }
            return $dataArr;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param array $field
     * @param array $profileData
     *
     * @return bool|float|int|string|null
     */
    private function getSchemaFieldValue(array $field, array $profileData)
    {
        try {
            $codeName = $field['code_name'];
            if (is_null($profileData[$codeName])) {
                return null;
            }

            $value = $profileData[$codeName];
            if ($field['type'] === 'integer') {
                if (in_array($codeName, ProfilesIndex::DATETIME_FIELDS)) {
                    $value = $this->apsisDateHelper->formatDateForPlatformCompatibility($value);
                } elseif (in_array($codeName, ProfilesIndex::PHONE_FIELDS)) {
                    if ($codeName === 'billing_telephone' && isset($profileData['billing_country'])) {
                        $country = $profileData['billing_country'];
                    } elseif ($codeName === 'delivery_telephone' && isset($profileData['delivery_country'])) {
                        $country = $profileData['delivery_country'];
                    }

                    if (isset($country)) {
                        $value = $this->apsisCoreHelper->validateAndFormatMobileNumber($country, $value);
                    }
                }
                return is_null($value) || $value === '' ? null : (integer) $value;
            } elseif ($field['type'] === 'double') {
                return $this->apsisCoreHelper->round($value);
            } elseif ($field['type'] === 'string') {
                if ($codeName === 'subscriber_status' && isset(self::SUBSCRIBER_STATUS_MAP[$value])) {
                    $value = self::SUBSCRIBER_STATUS_MAP[$value];
                }
                return (string) $value;
            } elseif ($field['type'] === 'boolean' && (is_bool($value) || in_array($value, [0, 1]))) {
                return (boolean) $value;
            }

            return null;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param Profile $profile
     * @param string $attributeCode
     *
     * @return bool|float|int|string|null
     */
    private function getCustomerAttributeValue(Profile $profile, string $attributeCode)
    {
        try {
            $attribute = $this->eavConfig->getAttribute('customer', $attributeCode);
            if (! $attribute->getId()) {
                return null;
            }

            $customer = $this->customerFactory->create()->load($profile->getCustomerId());
            $value = $customer->getData($attributeCode);
            if (is_null($value)) {
                return null;
            }

            if (in_array($attributeCode, ['default_billing', 'default_shipping'])) {
                $address = $customer->getAddressById($value);
                if ($address->getId()) {
                    return sprintf(
                        '%s, %s, %s, %s, %s, %s',
                        $address->getName(),
                        $address->getStreetFull(),
                        $address->getRegion(),
                        $address->getCity(),
                        $address->getCountryModel()->getName(),
                        $address->getTelephone()
                    );
                }
            }

            if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'])) {
                $options = $attribute->getSource()->getAllOptions();
                if (! is_array($options)) {
                    return null;
                }

                if ($attribute->getFrontendInput() === 'select') {
                    foreach ($options as $option) {
                        if ($option['value'] == $value) {
                            $optionLabel = $option['label'];
                            break;
                        }
                    }
                    return isset($optionLabel) ? (string) $optionLabel : null;
                } elseif ($attribute->getFrontendInput() === 'multiselect') {
                    $selectedOptions = explode(',', $value);
                    foreach ($options as $option) {
                        if (in_array($option['value'], $selectedOptions)) {
                            $values[] = $option['label'];
                        }
                    }
                    return isset($values) ? implode(',', $values) : null;
                }
            }

            if (in_array($attribute->getFrontendInput(), ['date', 'datetime'])) {
                return (int) $this->apsisDateHelper->formatDateForPlatformCompatibility($value);
            }

            if (in_array($attribute->getFrontendInput(), ['price', 'weight'])) {
                return $this->apsisCoreHelper->round($value);
            }

            if ($attribute->getFrontendInput() === 'boolean' && (is_bool($value) || in_array($value, [0, 1]))) {
                return (bool) $value;
            }

            return (string) $value;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param bool $addPagination
     *
     * @return int|AbstractCollection
     */
    private function getProfileCollectionForRecords(bool $addPagination)
    {
        try {
            $collection = $this->profileCollectionFactory->create()
                ->addFieldToFilter('profile_data', ['neq' => '']);
            $collection = $this->addStoreFilterOnCollection($collection);
            return $addPagination ? $this->setPaginationOnCollection($collection, 'id') : $collection;
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return 500;
        }
    }
}
