<?php

namespace Apsis\One\Model\Service;

use Apsis\One\Model\ResourceModel\Profile\CollectionFactory as ProfileCollectionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;

class Profile extends AbstractHelper
{
    /**
     * @var ProfileCollectionFactory
     */
    private $profileCollectionFactory;

    /**
     * Profile constructor.
     *
     * @param Context $context
     * @param ProfileCollectionFactory $profileCollectionFactory
     */
    public function __construct(
        Context $context,
        ProfileCollectionFactory $profileCollectionFactory
    ) {
        $this->profileCollectionFactory = $profileCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @param string $email
     * @param int $storeId
     *
     * @return bool|DataObject
     */
    public function getProfileByEmailAndStoreId(string $email, int $storeId)
    {
        return $this->profileCollectionFactory->create()
            ->loadByEmailAndStoreId($email, $storeId);
    }
}
