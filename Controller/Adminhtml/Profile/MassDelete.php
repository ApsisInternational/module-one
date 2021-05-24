<?php

namespace Apsis\One\Controller\Adminhtml\Profile;

use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Backend\App\Action;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;

class MassDelete extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Apsis_One::profile';

    /**
     * @var ProfileResource
     */
    public $profileResource;

    /**
     * @var ProfileCollectionFactory
     */
    public $profileCollectionFactory;

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param ApsisLogHelper $apsisLogHelper
     * @param ProfileResource $subscriberResource
     * @param Filter $filter
     * @param ProfileCollectionFactory $subscriberCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisLogHelper $apsisLogHelper,
        ProfileResource $subscriberResource,
        Filter $filter,
        ProfileCollectionFactory $subscriberCollectionFactory
    ) {
        $this->apsisLogHelper = $apsisLogHelper;
        $this->filter = $filter;
        $this->profileCollectionFactory = $subscriberCollectionFactory;
        $this->profileResource = $subscriberResource;
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
            $collection = $this->filter->getCollection($this->profileCollectionFactory->create());
            $collectionSize = $collection->getSize();
            $ids = $collection->getAllIds();
            foreach ($collection as $item) {
                $this->profileResource->delete($item);
            }

            $this->apsisLogHelper->debug(
                __METHOD__,
                ['Total Deleted' => $collectionSize, 'Profile Ids' => implode(", ", $ids)]
            );
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            $this->messageManager->addErrorMessage(__('An error happen during execution. Please check logs'));
        }
        return $resultRedirect->setPath('*/*/');
    }
}
