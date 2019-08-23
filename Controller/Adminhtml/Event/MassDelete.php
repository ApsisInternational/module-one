<?php

namespace Apsis\One\Controller\Adminhtml\Event;

use Apsis\One\Helper\Core as ApsisCoreHelper;
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
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param EventResource $eventResource
     * @param Filter $filter
     * @param EventCollectionFactory $eventCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        EventResource $eventResource,
        Filter $filter,
        EventCollectionFactory $eventCollectionFactory
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->filter = $filter;
        $this->eventCollectionFactory = $eventCollectionFactory;
        $this->eventResource = $eventResource;
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
            $collection = $this->filter->getCollection($this->eventCollectionFactory->create());
            $collectionSize = $collection->getSize();

            foreach ($collection as $item) {
                $this->eventResource->delete($item);
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__NAMESPACE__, __METHOD__, $e->getMessage());
            $this->messageManager->addErrorMessage(__('An error happen during execution. Please check logs'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
