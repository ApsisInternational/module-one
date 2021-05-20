<?php

namespace Apsis\One\Block\Adminhtml\Config\Event;

use Exception;
use Magento\Config\Block\System\Config\Form\Field;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class HistoricalData extends Field
{
    const TYPES = [
        'apsis_one_events_events_order_historical_event_duration' =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_ORDER_HISTORY_DONE_FLAG,
        'apsis_one_events_events_cart_historical_event_duration' =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_QUOTE_HISTORY_DONE_FLAG,
        'apsis_one_events_events_review_historical_event_duration' =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_REVIEW_HISTORY_DONE_FLAG,
        'apsis_one_events_events_wishlist_historical_event_duration' =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_WISHLIST_HISTORY_DONE_FLAG
    ];

    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * HistoricalData constructor.
     *
     * @param Context $context
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        ApsisCoreHelper $apsisCoreHelper,
        array $data = []
    ) {
        $this->apsisCoreHelper = $apsisCoreHelper;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        if ($element->getValue() && $this->getHistoryDoneFlagForScope($element->getId())) {
            $element->setDisabled('disabled');
        }
        return parent::_getElementHtml($element);
    }

    /**
     * @param string $elementId
     *
     * @return bool
     */
    private function getHistoryDoneFlagForScope(string $elementId)
    {
        try {
            $scope = $this->apsisCoreHelper->getSelectedScopeInAdmin();
            if ($scope['context_scope'] === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
                return $this->isEventHistoryDoneFlagExistOnGivenStore(
                    self::TYPES[$elementId],
                    $this->apsisCoreHelper->getAllStoreIds()
                );
            }
            if ($scope['context_scope'] === ScopeInterface::SCOPE_WEBSITES) {
                return $this->isEventHistoryDoneFlagExistOnGivenStore(
                    self::TYPES[$elementId],
                    $this->apsisCoreHelper->getAllStoreIdsFromWebsite($scope['context_scope_id'])
                );
            }
            if ($scope['context_scope'] === ScopeInterface::SCOPE_STORES) {
                return (boolean) $this->apsisCoreHelper->getMappedValueFromSelectedScope(
                    self::TYPES[$elementId]
                );
            }
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
        return false;
    }

    /**
     * @param string $path
     * @param array $storeIds
     *
     * @return bool
     */
    public function isEventHistoryDoneFlagExistOnGivenStore(string $path, array $storeIds)
    {
        try {
            $collection = $this->apsisCoreHelper->getConfigDataCollection()
                ->addFieldToFilter('scope', ScopeInterface::SCOPE_STORES)
                ->addFieldToFilter('scope_id', ['in' => $storeIds])
                ->addFieldToFilter('path', $path);
            return (boolean) $collection->getSize();
        } catch (Exception $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
