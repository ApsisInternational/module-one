<?php

namespace Apsis\One\Controller\Adminhtml\Profile;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Profile as ProfileService;
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
    const ADMIN_RESOURCE = 'Apsis_One::profile';

    /**
     * @var ProfileCollectionFactory
     */
    public $profileCollectionFactory;

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var ProfileService
     */
    private $profileService;

    /**
     * MassDelete constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisLogHelper
     * @param Filter $filter
     * @param ProfileService $profileService
     * @param ProfileCollectionFactory $subscriberCollectionFactory
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisLogHelper,
        Filter $filter,
        ProfileService $profileService,
        ProfileCollectionFactory $subscriberCollectionFactory
    ) {
        $this->profileService = $profileService;
        $this->apsisCoreHelper = $apsisLogHelper;
        $this->filter = $filter;
        $this->profileCollectionFactory = $subscriberCollectionFactory;
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
            $collection = $this->profileCollectionFactory->create();
            $collection = $this->filter->getCollection($collection);
            $collectionSize = $collection->getSize();

            $profileIds = $collection->getAllIds();
            $this->profileService->resetProfiles(__METHOD__, [], $profileIds);

            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been reset.', $collectionSize));
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            $this->messageManager->addErrorMessage(__('An error happen during execution. Please check logs'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
