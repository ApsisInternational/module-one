<?php

namespace Apsis\One\Observer\Adminhtml;

use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\ResourceModel\Profile;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SectionChange implements ObserverInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Event
     */
    private $eventResource;

    /**
     * @var Profile
     */
    private $profileResource;

    /**
     * SectionChange constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Context $context
     * @param Profile $profileResource
     * @param Event $eventResource
     */
    public function __construct(
        ApsisCoreHelper $apsisCoreHelper,
        Context $context,
        Profile $profileResource,
        Event $eventResource
    ) {
        $this->profileResource = $profileResource;
        $this->eventResource = $eventResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->context = $context;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     */
    public function execute(Observer $observer)
    {
        try {
            if (! empty($paths = $observer->getEvent()->getChangedPaths()) && is_array($paths) &&
                in_array("apsis_one_mappings/section_mapping/section", $paths)
            ) {
                $this->resetProfileAndEvents();
                $this->removeMappings();
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e->getMessage(), $e->getTraceAsString());
        }

        return $this;
    }

    /**
     * @return array
     */
    private function getStoreIds()
    {
        if ($storeId = $this->context->getRequest()->getParam('store')) {
            return [$storeId];
        }

        if ($websiteId = $this->context->getRequest()->getParam('website')) {
            return $this->apsisCoreHelper->getAllStoreIdsFromWebsite($websiteId);
        }

        return [];
    }

    /**
     * Reset profiles and events
     */
    private function resetProfileAndEvents()
    {
        $storeIds = $this->getStoreIds();
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
        $format = 'User changed section for SCOPE: %s with ID: %s';
        $string = sprintf($format, $scope['context_scope'], $scope['context_scope_id']);
        $this->apsisCoreHelper->log($string);
    }
}
