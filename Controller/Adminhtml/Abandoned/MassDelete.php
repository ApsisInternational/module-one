<?php

namespace Apsis\One\Controller\Adminhtml\Abandoned;

use Apsis\One\Helper\Core as ApsisCoreHelper;
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
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param AbandonedResource $abandonedResource
     * @param Filter $filter
     * @param AbandonedCollectionFactory $abandonedCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        AbandonedResource $abandonedResource,
        Filter $filter,
        AbandonedCollectionFactory $abandonedCollectionFactory
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->filter = $filter;
        $this->abandonedCollectionFactory = $abandonedCollectionFactory;
        $this->abandonedResource = $abandonedResource;
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
            $collection = $this->filter->getCollection($this->abandonedCollectionFactory->create());
            $collectionSize = $collection->getSize();

            foreach ($collection as $item) {
                $this->abandonedResource->delete($item);
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));
        } catch (Exception $e) {
            $this->apsisCoreHelper->logMessage(__CLASS__, __METHOD__, $e->getMessage());
            $this->messageManager->addErrorMessage(__('An error happen during execution. Please check logs'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
