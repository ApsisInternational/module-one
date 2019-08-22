<?php

namespace Apsis\One\Model\Config\Source\Datamapping;

use Magento\Framework\Data\OptionSourceInterface;
use Apsis\One\Helper\Core as ApsisCoreHelper;
use Apsis\One\Helper\Config as ApsisConfigHelper;

class Attributes implements OptionSourceInterface
{
    /**
     * @var ApsisCoreHelper
     */
    private $apsisCoreHelper;

    /**
     * Attributes constructor.
     *
     * @param ApsisCoreHelper $apsisCoreHelper
     */
    public function __construct(ApsisCoreHelper $apsisCoreHelper)
    {
        $this->apsisCoreHelper = $apsisCoreHelper;
    }

    /**
     *  Attribute options
     *
     * @return array
     */
    public function toOptionArray()
    {
        if (! $this->apsisCoreHelper->isEnabledForSelectedScopeInAdmin()) {
            return [['value' => '0', 'label' => __('-- Please Enable Account First --')]];
        }

        if (! $this->apsisCoreHelper->getMappedValueFromSelectedScope(
            ApsisConfigHelper::CONFIG_APSIS_ONE_MAPPINGS_SECTION_SECTION
        )) {
            return [['value' => '0', 'label' => __('-- Map & Save Section First --')]];
        }

        //default data option
        $fields[] = ['value' => '0', 'label' => __('-- Please Select --')];

        /**
         * @todo fetch from account set at selected scope
         */
        $fields[] = ['value' => 'subscriber_id', 'label' => 'SUBSCRIBER ID'];
        $fields[] = ['value' => 'subscriber_email', 'label' => 'SUBSCRIBER EMAIL'];
        $fields[] = ['value' => 'subscriber_status', 'label' => 'SUBSCRIBER STATUS'];
        $fields[] = ['value' => 'website_id', 'label' => 'WEBSITE ID'];
        $fields[] = ['value' => 'store_id', 'label' => 'STORE ID'];
        $fields[] = ['value' => 'website_name', 'label' => 'WEBSITE NAME'];
        $fields[] = ['value' => 'store_name', 'label' => 'STORE NAME'];
        $fields[] = ['value' => 'title', 'label' => 'TITLE'];
        $fields[] = ['value' => 'customer_id', 'label' => 'CUSTOMER ID'];
        $fields[] = ['value' => 'first_name', 'label' => 'FIRST NAME'];
        $fields[] = ['value' => 'last_name', 'label' => 'LAST NAME'];
        $fields[] = ['value' => 'dob', 'label' => 'DOB'];
        $fields[] = ['value' => 'gender', 'label' => 'GENDER'];
        $fields[] = ['value' => 'created_at', 'label' => 'CREATED AT'];
        $fields[] = ['value' => 'last_logged_in_date', 'label' => 'LAST LOGGED IN DATE'];
        $fields[] = ['value' => 'customer_group', 'label' => 'CUSTOMER GROUP'];
        $fields[] = ['value' => 'review_count', 'label' => 'REVIEW COUNT'];
        $fields[] = ['value' => 'last_review_date', 'label' => 'LAST REVIEW DATE'];
        $fields[] = ['value' => 'billing_address_line_1', 'label' => 'BILLING ADDRESS LINE 1'];
        $fields[] = ['value' => 'billing_address_line_2', 'label' => 'BILLING ADDRESS LINE 2'];
        $fields[] = ['value' => 'billing_city', 'label' => 'BILLING CITY'];
        $fields[] = ['value' => 'billing_state', 'label' => 'BILLING STATE'];
        $fields[] = ['value' => 'billing_country', 'label' => 'BILLING COUNTRY'];
        $fields[] = ['value' => 'billing_postcode', 'label' => 'BILLING POSTCODE'];
        $fields[] = ['value' => 'billing_telephone', 'label' => 'BILLING TELEPHONE'];
        $fields[] = ['value' => 'billing_company', 'label' => 'BILLING COMPANY'];
        $fields[] = ['value' => 'shipping_address_line_1', 'label' => 'SHIPPING ADDRESS LINE 1'];
        $fields[] = ['value' => 'shipping_address_line_2', 'label' => 'SHIPPING ADDRESS LINE 2'];
        $fields[] = ['value' => 'shipping_city', 'label' => 'SHIPPING CITY'];
        $fields[] = ['value' => 'shipping_state', 'label' => 'SHIPPING STATE'];
        $fields[] = ['value' => 'shipping_country', 'label' => 'SHIPPING COUNTRY'];
        $fields[] = ['value' => 'shipping_postcode', 'label' => 'SHIPPING POSTCODE'];
        $fields[] = ['value' => 'shipping_telephone', 'label' => 'SHIPPING TELEPHONE'];
        $fields[] = ['value' => 'shipping_company', 'label' => 'SHIPPING COMPANY'];
        $fields[] = ['value' => 'last_cart_id', 'label' => 'LAST CART ID'];
        $fields[] = ['value' => 'last_increment_id', 'label' => 'LAST INCREMENT ID'];
        $fields[] = ['value' => 'last_purchase_date', 'label' => 'LAST PURCHASE DATE'];
        $fields[] = ['value' => 'total_number_of_orders', 'label' => 'TOTAL NUMBER OF ORDERS'];
        $fields[] = ['value' => 'average_order_value', 'label' => 'AVERAGE ORDER VALUE'];
        $fields[] = ['value' => 'total_spent', 'label' => 'TOTAL SPENT'];
        $fields[] = ['value' => 'ac_token', 'label' => 'AC TOKEN'];

        return $fields;
    }
}
