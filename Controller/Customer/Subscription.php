<?php

namespace Apsis\One\Controller\Customer;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Sync\Profiles\Subscribers;
use Apsis\One\Plugin\Customer\NewsletterManageIndexPlugin;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\ScopeInterface;
use Throwable;

class Subscription extends Action
{
    const MAGENTO_NEWSLETTER_MANAGE_URL = 'newsletter/manage/';
    const MAGENTO_CUSTOMER_ACCOUNT_URL = 'customer/account/';

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Validator
     */
    private $formKeyValidator;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * Subscription constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Session $customerSession
     * @param Validator $formKeyValidator
     * @param CustomerRepository $customerRepository
     * @param SubscriberFactory $subscriberFactory
     * @param ProfileCollectionFactory $profileCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        Session $customerSession,
        Validator $formKeyValidator,
        CustomerRepository $customerRepository,
        SubscriberFactory $subscriberFactory,
        ProfileCollectionFactory $profileCollectionFactory
    ) {
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->subscriberFactory = $subscriberFactory;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->formKeyValidator = $formKeyValidator;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        if (! $this->formKeyValidator->validate($this->getRequest())) {
            return $this->_redirect(self::MAGENTO_CUSTOMER_ACCOUNT_URL);
        }

        //Update Magento Newsletter Subscription
        $this->updateMagentoNewsletterSubscription();

        try {
            if ((boolean) $this->getRequest()->getParam('is_subscribed', false) &&
                ! empty($subscriber = $this->subscriberFactory->create()->loadByCustomerId(
                    $this->customerSession->getCustomerId()
                )) && ! empty($profile = $this->profileCollectionFactory->create()->loadBySubscriberId(
                    $subscriber->getSubscriberId()
                ))
            ) {
                $preUpdateConsents = $this->customerSession->getPreUpdateConsents();
                $this->customerSession->unsPreUpdateConsents();
                if (! empty($preUpdateConsents)) {
                    $check = $this->evaluateAndUpdateConsent($preUpdateConsents, $profile);
                    if ($check) {
                        $this->messageManager->addSuccessMessage(__('The subscription topics has been saved.'));
                    } else {
                        $this->messageManager
                            ->addNoticeMessage(__('We could not save all subscription topics, please try again later'));
                    }
                }
                return $this->_redirect(NewsletterManageIndexPlugin::APSIS_NEWSLETTER_MANAGE_URL);
            }
            return $this->_redirect(self::MAGENTO_NEWSLETTER_MANAGE_URL);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addErrorMessage(__('An error occurred while saving your subscription topics.'));
            return $this->_redirect(self::MAGENTO_NEWSLETTER_MANAGE_URL);
        }
    }

    /**
     * @param array $preUpdateConsents
     * @param Profile $profile
     *
     * @return bool
     */
    private function evaluateAndUpdateConsent(array $preUpdateConsents, Profile $profile)
    {
        $postConsents = $this->getRequest()->getParam('topic_subscriptions', []);
        $check = true;
        foreach ($preUpdateConsents as $preUpdateConsent) {
            if ($preUpdateConsent['consent'] === true && ! in_array($preUpdateConsent['value'], $postConsents)) {
                //create consent (Remove)
                $check = $this->createConsent($profile, Subscribers::CONSENT_TYPE_OPT_OUT, $preUpdateConsent['value']);
            } elseif ($preUpdateConsent['consent'] === false && in_array($preUpdateConsent['value'], $postConsents)) {
                //create consent (Add)
                $check = $this->createConsent($profile, Subscribers::CONSENT_TYPE_OPT_IN, $preUpdateConsent['value']);
            }
        }
        return $check;
    }

    /**
     * @param Profile $profile
     * @param string $type
     * @param string $consentTopic
     *
     * @return bool
     */
    private function createConsent(Profile $profile, string $type, string $consentTopic)
    {
        $store = $this->apsisCoreHelper->getStore($profile->getSubscriberStoreId());
        $client = $this->apsisCoreHelper->getApiClient(
            ScopeInterface::SCOPE_STORES,
            $store->getId()
        );
        $sectionDiscriminator = $this->apsisCoreHelper->getStoreConfig(
            $store,
            ApsisConfigHelper::MAPPINGS_SECTION_SECTION
        );
        $keySpaceDiscriminator = $this->apsisCoreHelper->getKeySpaceDiscriminator($sectionDiscriminator);

        if (empty($client) || empty($sectionDiscriminator) || empty($keySpaceDiscriminator) || empty($consentTopic)) {
            return false;
        }

        //Create consent
        $result = $client->createConsent(
            $keySpaceDiscriminator,
            $profile->getIntegrationUid(),
            $sectionDiscriminator,
            $consentTopic,
            Profile::EMAIL_CHANNEL_DISCRIMINATOR,
            $type
        );

        if ($result === null) {
            $info = [
                'Action' => 'createConsent',
                'Consent Type' => $type,
                'Profile Id' => $profile->getIntegrationUid(),
                'Section' => $sectionDiscriminator,
                'Topic' => $consentTopic
            ];
            $this->apsisCoreHelper->debug(__METHOD__, $info);
        }

        return ($result === false || is_string($result)) ? false : true;
    }

    /**
     * Process general subscription
     */
    private function updateMagentoNewsletterSubscription()
    {
        $customerId = $this->customerSession->getCustomerId();
        if ($customerId === null) {
            $this->messageManager->addError(__('Something went wrong while saving your subscription.'));
        } else {
            try {
                $customer = $this->customerRepository->getById($customerId);
                $storeId = $this->apsisCoreHelper->getStore()->getId();
                $customer->setStoreId($storeId);
                $isSubscribedParam = (boolean) $this->getRequest()->getParam('is_subscribed', false);
                if (! empty($customer->getExtensionAttributes())) {
                    if ($isSubscribedParam !== $customer->getExtensionAttributes()->getIsSubscribed()) {
                        $this->updateForCustomerWithExtensionAttributes($customer, $isSubscribedParam, $customerId);
                    } else {
                        $this->messageManager->addSuccess(__('We have updated your subscription.'));
                    }
                } else {
                    $this->updateForCustomerWithoutExtensionAttributes($isSubscribedParam, $customer);
                }
            } catch (Throwable $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e);
                $this->messageManager->addError(__('Something went wrong while saving your subscription.'));
            }
        }
    }

    /**
     * @param CustomerInterface $customer
     * @param bool $isSubscribedParam
     * @param int $customerId
     *
     */
    private function updateForCustomerWithExtensionAttributes(
        CustomerInterface $customer,
        bool $isSubscribedParam,
        int $customerId
    ) {
        try {
            $customer->setData('ignore_validation_flag', true);
            $this->customerRepository->save($customer);
            if ($isSubscribedParam) {
                $subscribeModel = $this->subscriberFactory->create()->subscribeCustomerById($customerId);
                if ($subscribeModel->getStatus() == Subscriber::STATUS_SUBSCRIBED) {
                    $this->messageManager->addSuccess(__('We have saved your subscription.'));
                } else {
                    $this->messageManager->addSuccess(__('A confirmation request has been sent.'));
                }
            } else {
                $this->subscriberFactory->create()->unsubscribeCustomerById($customerId);
                $this->messageManager->addSuccess(__('We have removed your newsletter subscription.'));
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addError(__('Something went wrong while saving your subscription.'));
        }
    }

    /**
     * @param bool $isSubscribedParam
     * @param CustomerInterface $customer
     */
    private function updateForCustomerWithoutExtensionAttributes(bool $isSubscribedParam, CustomerInterface $customer)
    {
        try {
            $this->customerRepository->save($customer);
            if ($isSubscribedParam) {
                $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
                $this->messageManager->addSuccess(__('We saved the subscription.'));
            } else {
                $this->subscriberFactory->create()->unsubscribeCustomerById($customer->getId());
                $this->messageManager->addSuccess(__('We removed the subscription.'));
            }
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addError(__('Something went wrong while saving your subscription.'));
        }
    }
}
