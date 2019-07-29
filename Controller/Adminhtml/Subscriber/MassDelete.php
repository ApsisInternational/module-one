<?php

namespace Apsis\One\Controller\Adminhtml\Subscriber;

use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
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
     * MassDelete constructor.
     *
     * @param Context $context
     * @param SubscriberResource $subscriberResource
     * @param Filter $filter
     * @param SubscriberCollectionFactory $subscriberCollectionFactory
     */
    public function __construct(
        Context $context,
        SubscriberResource $subscriberResource,
        Filter $filter,
        SubscriberCollectionFactory $subscriberCollectionFactory
    ) {
        $this->filter = $filter;
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
        $this->subscriberResource = $subscriberResource;
        parent::__construct($context);
    }

    /**
     * @return Redirect|ResponseInterface|ResultInterface
     *
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->subscriberCollectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $item) {
            $this->subscriberResource->delete($item);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
