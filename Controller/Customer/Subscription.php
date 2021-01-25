<?php

namespace Apsis\One\Controller\Customer;

use Apsis\One\Model\Profile;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Plugin\Customer\NewsletterManageIndexPlugin;
use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;

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
     * @var ProfileResource
     */
    private $profileResource;

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
     * @param ProfileResource $profileResource
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        Session $customerSession,
        Validator $formKeyValidator,
        CustomerRepository $customerRepository,
        SubscriberFactory $subscriberFactory,
        ProfileCollectionFactory $profileCollectionFactory,
        ProfileResource $profileResource
    ) {
        $this->profileResource = $profileResource;
        $this->profileCollectionFactory = $profileCollectionFactory;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->subscriberFactory = $subscriberFactory;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->formKeyValidator = $formKeyValidator;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
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
                $subscription = implode(',', $this->getRequest()->getParam('topic_subscriptions', []));
                $profile->setTopicSubscription(empty($subscription) ? '-' : $subscription)
                    ->setSubscriberSyncStatus(Profile::SYNC_STATUS_PENDING);
                $this->profileResource->save($profile);
                $this->messageManager->addSuccessMessage(__('The subscription topics has been saved.'));
                return $this->_redirect(NewsletterManageIndexPlugin::APSIS_NEWSLETTER_MANAGE_URL);
            }
            return $this->_redirect(self::MAGENTO_NEWSLETTER_MANAGE_URL);
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            $this->messageManager->addErrorMessage(__('An error occurred while saving your subscription topics.'));
            return $this->_redirect(self::MAGENTO_NEWSLETTER_MANAGE_URL);
        }
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
            } catch (Exception $e) {
                $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
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
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
            $this->messageManager->addError(__('Something went wrong while saving your subscription.'));
        }
    }
}
