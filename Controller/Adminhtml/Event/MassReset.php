<?php

namespace Apsis\One\Controller\Adminhtml\Event;

use Apsis\One\Model\ResourceModel\Event as EventResource;
use Apsis\One\Model\ResourceModel\Event\CollectionFactory as EventCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Throwable;

class MassReset extends Action
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
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisLogHelper
     * @param EventResource $eventResource
     * @param Filter $filter
     * @param EventCollectionFactory $eventCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisLogHelper,
        EventResource $eventResource,
        Filter $filter,
        EventCollectionFactory $eventCollectionFactory
    ) {
        $this->apsisCoreHelper = $apsisLogHelper;
        $this->filter = $filter;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->eventResource = $eventResource;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $collection = $this->filter->getCollection($this->eventCollectionFactory->create());
            $collectionSize = $collection->getSize();
            $ids = $collection->getAllIds();
            $this->eventResource->resetEvents($this->apsisCoreHelper, [], $ids);

            $this->apsisCoreHelper->debug(
                __METHOD__,
                ['Total Reset' => $collectionSize, 'Event Ids' => implode(", ", $ids)]
            );
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been reset.', $collectionSize));
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addErrorMessage(__('An error happen during execution. Please check logs'));
        }
        return $resultRedirect->setPath('*/*/');
    }
}
