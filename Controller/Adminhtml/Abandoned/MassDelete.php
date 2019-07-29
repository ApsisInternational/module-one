<?php

namespace Apsis\One\Controller\Adminhtml\Abandoned;

use Exception;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Backend\App\Action;
use Apsis\One\Model\ResourceModel\Abandoned as AbandonedResource;
use Apsis\One\Model\ResourceModel\Abandoned\CollectionFactory as AbandonedCollectionFactory;
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
    const ADMIN_RESOURCE = 'Apsis_One::abandoned';

    /**
     * @var AbandonedResource
     */
    public $abandonedResource;

    /**
     * @var AbandonedCollectionFactory
     */
    public $abandonedCollectionFactory;

    /**
     * @var Filter
     */
    private $filter;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param AbandonedResource $abandonedResource
     * @param Filter $filter
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     */
    public function __construct(
        Context $context,
        AbandonedResource $abandonedResource,
        Filter $filter,
        AbandonedCollectionFactory $abandonedCollectionFactory
    ) {
        $this->filter = $filter;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->abandonedResource = $abandonedResource;
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
        $collection = $this->filter->getCollection($this->abandonedCollectionFactory->create());
        $collectionSize = $collection->getSize();

        foreach ($collection as $item) {
            $this->abandonedResource->delete($item);
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
