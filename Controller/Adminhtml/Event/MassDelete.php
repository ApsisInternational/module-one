<?php

namespace Apsis\One\Controller\Adminhtml\Event;

use Exception;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Backend\App\Action;
use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class MassDelete extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::event';

    /**
     * @var EventResource
     */
    public $eventResource;

    /**
     * @var EventCollectionFactory
     */
    public $eventCollectionFactory;

    /**
     * @var Filter
     */
    private $filter;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param EventResource $eventResource
     * @param Filter $filter
     * @param EventCollectionFactory $eventCollectionFactory
     */
    public function __construct(
        Context $context,
        EventResource $eventResource,
        Filter $filter,
        EventCollectionFactory $eventCollectionFactory
    ) {
        $this->filter = $filter;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->eventResource = $eventResource;
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
        $collection = $this->filter->getCollection($this->eventCollectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $item) {
            $this->eventResource->delete($item);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
