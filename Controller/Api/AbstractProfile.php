<?php

namespace Apsis\One\Controller\Api;

use Apsis\One\Controller\Api\Profiles\Index as ProfilesIndex;
use Apsis\One\Controller\Api\Consents\Index as ConsentsIndex;
use Apsis\One\Model\ProfileModel;
use libphonenumber\PhoneNumberUtil;
use Magento\Customer\Model\ResourceModel\Group\Collection as GroupCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Apsis\One\Service\ProfileService;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Data\Collection;
use Magento\Framework\Encryption\EncryptorInterface;
use Apsis\One\Model\ResourceModel\AbstractCollection;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection as MagentoAbstractCollection;
use Magento\Newsletter\Model\Subscriber;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
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
     * @var EavConfig
     */
    protected EavConfig $eavConfig;

    /**
     * @var CustomerResource
     */
    protected CustomerResource $customerResource;

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ProfileService $service
     * @param CustomerFactory $customerFactory
     * @param EncryptorInterface $encryptor
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param EavConfig $eavConfig
     * @param CustomerResource $customerResource
     */
    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        ProfileService $service,
        CustomerFactory $customerFactory,
        EncryptorInterface $encryptor,
        GroupCollectionFactory $groupCollectionFactory,
        EavConfig $eavConfig,
        CustomerResource $customerResource
    ) {
        $this->customerResource = $customerResource;
        $this->eavConfig = $eavConfig;
        $this->groupCollectionFactory = $groupCollectionFactory;
        parent::__construct($request, $response, $service, $customerFactory, $encryptor);
    }

    /**
     * @return GroupCollection
     */
    protected function getGroupCollection(): GroupCollection
    {
        return $this->groupCollectionFactory->create();
    }

    /**
     * @param int $id
     *
     * @return bool|int
     */
    protected function doesGroupIdExist(int $id): bool|int
    {
        try {
            $collection = $this->getGroupCollection()
                ->addFieldToFilter('main_table.customer_group_id', $id);
            return $collection->getSize() ? true : 404;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getGroupRecordsCount(): int|array
    {
        try {
            $collection = $this->getProfileCollectionByGroupId(false);
            return is_int($collection) ? $collection : ['count' => $collection->getSize()];
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getGroupRecords(): int|array
    {
        try {
            $collection = $this->getProfileCollectionByGroupId(true);
            if (is_int($collection)) {
                return $collection;
            }

            $records = [];
            /** @var ProfileModel $profile */
            foreach ($collection as $profile) {
                $records[] = [ProfilesIndex::ENTITY_NAME => $profile->getId()];
            }
            return $records;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param bool $addPagination
     *
     * @return int|AbstractCollection
     */
    private function getProfileCollectionByGroupId(bool $addPagination): AbstractCollection|int
    {
        try {
            $collection = $this->service
                ->getProfileCollection()
                ->getCollection(['group_id' => $this->taskId, 'store_id' => $this->store->getId()]);
            return $addPagination ? $this->setPaginationOnCollection($collection) : $collection;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param AbstractCollection|MagentoAbstractCollection $collection
     * @param string $field
     *
     * @return AbstractCollection|MagentoAbstractCollection
     */
    protected function setPaginationOnCollection(
        AbstractCollection|MagentoAbstractCollection $collection,
        string $field = 'id'
    ): AbstractCollection|MagentoAbstractCollection {
        $page = (int) $this->queryParams['page'] + 1;
        $pageSize = (int) $this->queryParams['page_size'];

        if ($collection instanceof AbstractCollection) {
            return $collection->setPaginationOnCollection($page, $pageSize, $field);
        }

        $collection->setOrder($field, Collection::SORT_ORDER_ASC);
        $collection->getSelect()->limitPage($page, $pageSize);
        return $collection;
    }

    /**
     * @return array|int
     */
    protected function getConsentsCount(): int|array
    {
        try {
            $collection = $this->getProfileCollectionForConsents(false);
            return is_int($collection) ? $collection : ['count' => $collection->getSize()];
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getConsents(): int|array
    {
        try {
            $collection = $this->getProfileCollectionForConsents(true);
            if (is_int($collection)) {
                return $collection;
            }

            $consents = [];
            /** @var ProfileModel $profile */
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
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return bool|int
     */
    protected function updateConsent(): bool|int
    {
        try {
            if (! is_numeric($this->requestBody['record_id']) ||
                $this->requestBody['consent_base_id'] !== ConsentsIndex::CONSENT_BASE_ID ||
                $this->requestBody['has_consented'] !== false
            ) {
                return 400;
            }

            return $this->service->updateSubscription((int) $this->requestBody['record_id']);
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param ProfileModel $profile
     *
     * @return bool|null
     */
    private function getConsentedStatus(ProfileModel $profile): ?bool
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
    private function getProfileCollectionForConsents(bool $addPagination): AbstractCollection|int
    {
        try {
            $fields = [
                'is_subscriber' => 1,
                'subscriber_status' => [Subscriber::STATUS_SUBSCRIBED, Subscriber::STATUS_UNSUBSCRIBED],
                'store_id' => $this->store->getId()
            ];
            $collection = $this->service
                ->getProfileCollection()
                ->getCollection($fields);
            return $addPagination ? $this->setPaginationOnCollection($collection) : $collection;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getProfilesCount(): int|array
    {
        try {
            $collection = $this->getProfileCollectionForRecords(false);
            return is_int($collection) ? $collection : ['count' => $collection->getSize()];
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @return array|int
     */
    protected function getProfiles(): int|array
    {
        try {
            $collection = $this->getProfileCollectionForRecords(true);
            if (is_int($collection)) {
                return $collection;
            }

            // If ids are provided than filter by ids on collection
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
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param ProfileModel $profile
     * @param array $requestedFields
     *
     * @return array
     */
    private function getProfileDataArr(ProfileModel $profile, array $requestedFields): array
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
            $this->service->logError(__METHOD__, $e);
            return [];
        }
    }

    /**
     * @param array $field
     * @param array $profileData
     *
     * @return bool|float|int|string|null
     */
    private function getSchemaFieldValue(array $field, array $profileData): float|bool|int|string|null
    {
        try {
            $codeName = $field['code_name'];
            if (is_null($profileData[$codeName])) {
                return null;
            }

            $value = $profileData[$codeName];
            if ($field['type'] === 'integer') {
                if (in_array($codeName, ProfilesIndex::DATETIME_FIELDS)) {
                    $value = $this->service->formatDateForPlatformCompatibility($value);
                } elseif (in_array($codeName, ProfilesIndex::PHONE_FIELDS)) {
                    if ($codeName === 'billing_telephone' && isset($profileData['billing_country'])) {
                        $country = $profileData['billing_country'];
                    } elseif ($codeName === 'delivery_telephone' && isset($profileData['delivery_country'])) {
                        $country = $profileData['delivery_country'];
                    }

                    if (isset($country)) {
                        $value = $this->validateAndFormatMobileNumber($country, $value);
                    }
                }
                return is_null($value) || $value === '' ? null : (integer) $value;
            } elseif ($field['type'] === 'double') {
                return round($value, 2);
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
            $this->service->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param ProfileModel $profile
     * @param string $attributeCode
     *
     * @return bool|float|int|string|null
     */
    private function getCustomerAttributeValue(ProfileModel $profile, string $attributeCode): float|bool|int|string|null
    {
        try {
            $attribute = $this->eavConfig->getAttribute(ProfileService::ENTITY_CUSTOMER, $attributeCode);
            if (! $attribute->getId()) {
                return null;
            }

            $customer = $this->getCustomerModel();
            $this->customerResource->load($customer, $profile->getCustomerId());
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
                return (int) $this->service->formatDateForPlatformCompatibility($value);
            }

            if (in_array($attribute->getFrontendInput(), ['price', 'weight'])) {
                return round($value, 2);
            }

            if ($attribute->getFrontendInput() === 'boolean' && (is_bool($value) || in_array($value, [0, 1]))) {
                return (bool) $value;
            }

            return (string) $value;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param bool $addPagination
     *
     * @return int|AbstractCollection
     */
    private function getProfileCollectionForRecords(bool $addPagination): AbstractCollection|int
    {
        try {
            $collection = $this->service
                ->getProfileCollection()
                ->getCollection('store_id', $this->store->getId());
            return $addPagination ? $this->setPaginationOnCollection($collection) : $collection;
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
            return 500;
        }
    }

    /**
     * @param string $countryCode
     * @param string $phoneNumber
     *
     * @return int|null
     */
    private function validateAndFormatMobileNumber(string $countryCode, string $phoneNumber): ?int
    {
        try {
            if (strlen($countryCode) === 2) {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $numberProto = $phoneUtil->parse($phoneNumber, $countryCode);
                if ($phoneUtil->isValidNumber($numberProto)) {
                    return (int) sprintf(
                        '%d%d',
                        (int) $numberProto->getCountryCode(),
                        (int) $numberProto->getNationalNumber()
                    );
                }
            }
        } catch (Throwable $e) {
            $this->service->logError(__METHOD__, $e);
        }

        return null;
    }
}
