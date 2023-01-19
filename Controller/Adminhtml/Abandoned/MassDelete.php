<?php

namespace Apsis\One\Controller\Adminhtml\Abandoned;

use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Throwable;

class MassDelete extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::abandoned';

    /**
     * @var AbandonedResource
     */
    public AbandonedResource $abandonedResource;

    /**
     * @var AbandonedCollectionFactory
     */
    public AbandonedCollectionFactory $abandonedCollectionFactory;

    /**
     * @var Filter
     */
    private Filter $filter;

    /**
     * @var ApsisLogHelper
     */
    private ApsisLogHelper $apsisLogHelper;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param ApsisLogHelper $apsisLogHelper
     * @param AbandonedResource $abandonedResource
     * @param Filter $filter
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisLogHelper $apsisLogHelper,
        AbandonedResource $abandonedResource,
        Filter $filter,
        AbandonedCollectionFactory $abandonedCollectionFactory
    ) {
        $this->apsisLogHelper = $apsisLogHelper;
        $this->filter = $filter;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->abandonedResource = $abandonedResource;
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $collection = $this->filter->getCollection($this->abandonedCollectionFactory->create());
            $collectionSize = $collection->getSize();
            $ids = $collection->getAllIds();
            foreach ($collection as $item) {
                $this->abandonedResource->delete($item);
            }

            $this->apsisLogHelper->debug(
                __METHOD__,
                ['Total Deleted' => $collectionSize, 'AC Ids' => implode(", ", $ids)]
            );
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));
        } catch (Throwable $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            $this->messageManager->addErrorMessage(__('An error happen during execution. Please check logs'));
        }
        return $resultRedirect->setPath('*/*/');
    }
}
