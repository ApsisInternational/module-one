<?php

namespace Apsis\One\Controller\Adminhtml\Subscriber;

use Apsis\One\Helper\Core as ApsisCoreHelper;
use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Backend\App\Action;
use Apsis\One\Model\ResourceModel\Subscriber as SubscriberResource;
use Apsis\One\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;

class MassDelete extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::subscriber';

    /**
     * @var SubscriberResource
     */
    public $subscriberResource;

    /**
     * @var SubscriberCollectionFactory
     */
    public $subscriberCollectionFactory;

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param SubscriberResource $subscriberResource
     * @param Filter $filter
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        SubscriberResource $subscriberResource,
        Filter $filter,
        SubscriberCollectionFactory $subscriberCollectionFactory
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->filter = $filter;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->subscriberResource = $subscriberResource;
        parent::__construct($context);
    }

    /**
     * @return Redirect|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $collection = $this->filter->getCollection($this->subscriberCollectionFactory->create());
            $collectionSize = $collection->getSize();

            foreach ($collection as $item) {
                $this->subscriberResource->delete($item);
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__CLASS__, __METHOD__, $e->getMessage());
            $this->messageManager->addErrorMessage(__('An error happen during execution. Please check logs'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
