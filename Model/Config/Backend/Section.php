<?php

namespace Apsis\One\Model\Config\Backend;

use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\ResourceModel\Profile;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Section extends Value
{
    const REGISTRY_NAME = 'section_change';

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Event
     */
    private $eventResource;

    /**
     * @var Profile
     */
    private $profileResource;

    /**
     * PastEvents constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Profile $profileResource
     * @param Event $eventResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ApsisCoreHelper $apsisCoreHelper,
        Profile $profileResource,
        Event $eventResource,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        $this->profileResource = $profileResource;
        $this->eventResource = $eventResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection
        );
    }

    /**
     * @return Value
     */
    public function afterSave()
    {
        if ($this->isValueChanged()) {
            $this->resetProfileAndEvents();
            $this->removeMappings();
            $this->_registry->unregister(self::REGISTRY_NAME);
            $this->_registry->register(self::REGISTRY_NAME, true, true);
        }
        return parent::afterSave();
    }

    /**
     * Reset profiles and events
     */
    private function resetProfileAndEvents()
    {
        $storeIds = $this->apsisCoreHelper->getStoreIdsBasedOnScope();
        $this->profileResource->resetProfiles($this->apsisCoreHelper, $storeIds);
        $this->eventResource->resetEvents($this->apsisCoreHelper, $storeIds);
    }

    /**
     * Remove attribute and consent list topic mapping data
     */
    private function removeMappings()
    {
        $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
        $configPaths = array_merge(
            ApsisConfigHelper::COMMON_ATTRIBUTE_LIST,
            ApsisConfigHelper::CUSTOMER_ATTRIBUTE_LIST,
            ApsisConfigHelper::SUBSCRIBER_ATTRIBUTE_LIST
        );
        foreach ($configPaths as $path) {
            $this->apsisCoreHelper->deleteConfigByScope(
                $path,
                $scope['context_scope'],
                $scope['context_scope_id']
            );
        }
        $this->apsisCoreHelper->deleteConfigByScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_TOPIC,
            $scope['context_scope'],
            $scope['context_scope_id']
        );

        //Clean cache
        $this->apsisCoreHelper->cleanCache();

        $format = 'User changed section for SCOPE: %s with ID: %s';
        $string = sprintf($format, $scope['context_scope'], $scope['context_scope_id']);
        $this->apsisCoreHelper->log($string);
    }
}
