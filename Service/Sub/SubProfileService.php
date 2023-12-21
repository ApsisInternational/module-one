<?php

namespace Apsis\One\Service\Sub;

use Apsis\One\Model\ProfileModel;
use Apsis\One\Model\ResourceModel\ProfileResource;
use Apsis\One\Service\BaseService;
use Apsis\One\Service\ProfileService;
use Magento\Customer\Model\Customer;
use Magento\Newsletter\Model\Subscriber;
use Apsis\One\Model\ProfileModelFactory;
use Throwable;

class SubProfileService
{
    /**
     * @var ProfileResource
     */
    public ProfileResource $profileResource;

    /**
     * @var ProfileModelFactory
     */
    private ProfileModelFactory $profileModelFactory;

    /**
     * @param ProfileResource $profileResource
     * @param ProfileModelFactory $profileModelFactory
     */
    public function __construct(
        ProfileResource $profileResource,
        ProfileModelFactory $profileModelFactory,
    ) {
        $this->profileModelFactory = $profileModelFactory;
        $this->profileResource = $profileResource;
    }

    /**
     * @return ProfileModel
     */
    private function getProfileModel(): ProfileModel
    {
        return $this->profileModelFactory->create();
    }

    /**
     * @param int $storeId
     * @param string $email
     * @param BaseService $baseService
     * @param int|null $subscriberId
     * @param int|null $customerId
     * @param int|null $groupId
     * @param int|null $subscriberStatus
     *
     * @return ProfileModel|null
     */
    public function createProfile(
        int $storeId,
        string $email,
        BaseService $baseService,
        int $subscriberId = null,
        int $customerId = null,
        int $groupId = null,
        int $subscriberStatus = null
    ): ?ProfileModel {
        try {
            $profile = $this->getProfileModel()
                ->setEmail($email)
                ->setStoreId($storeId);

            if ($customerId) {
                $profile->setCustomerId($customerId)
                    ->setGroupId($groupId)
                    ->setIsCustomer(1);
            }

            if ($subscriberId) {
                $profile->setSubscriberId($subscriberId)
                    ->setSubscriberStatus($subscriberStatus)
                    ->setIsSubscriber(1);
            }

            $this->profileResource->save($profile);
            return $profile;
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
            return null;
        }
    }

    /**
     * @param ProfileModel $profile
     * @param Customer|Subscriber $model
     * @param BaseService $baseService
     *
     * @return void
     */
    public function updateProfile(
        ProfileModel $profile,
        Customer|Subscriber $model,
        BaseService $baseService
    ): void {
        try {
            $profile->setErrorMessage('');

            if ($model instanceof Customer) {
                $profile->setCustomerId($model->getEntityId())
                    ->setGroupId($model->getGroupId())
                    ->setIsCustomer(1);
            }

            if ($model instanceof Subscriber) {
                $profile->setSubscriberId($model->getSubscriberId())
                    ->setIsSubscriber(1)
                    ->setSubscriberStatus($model->getSubscriberStatus());
            }

            $this->profileResource->save($profile);
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @param ProfileModel $profile
     * @param string $deleteType
     * @param BaseService $baseService
     *
     * @return bool|null
     */
    public function deleteProfile(ProfileModel $profile, string $deleteType, BaseService $baseService): ?bool
    {
        try {
            if ($deleteType === ProfileService::TYPE_CUSTOMER) {
                if ($profile->getIsSubscriber()) {
                    $profile->setCustomerId(null)
                        ->setGroupId(null)
                        ->setIsCustomer(0);
                    $proceedDelete = false;
                } else {
                    $proceedDelete = true;
                }
            }

            if ($deleteType === ProfileService::TYPE_SUBSCRIBER) {
                if ($profile->getIsCustomer()) {
                    $profile->setSubscriberId(null)
                        ->setIsSubscriber(0)
                        ->setSubscriberStatus(null);
                    $proceedDelete = false;
                } else {
                    $proceedDelete = true;
                }
            }

            if (isset($proceedDelete)) {
                if ($proceedDelete === true) {
                    $this->profileResource->delete($profile);
                    return true;
                }

                if ($proceedDelete === false) {
                    $this->profileResource->save($profile);
                    return false;
                }
            }
        } catch (Throwable $e) {
            $baseService->logError(__METHOD__, $e);
        }
        return null;
    }
}
