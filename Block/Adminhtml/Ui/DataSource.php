<?php

namespace Apsis\One\Block\Adminhtml\Ui;

use Magento\Ui\Component\Listing\Columns;

class DataSource extends Columns
{
    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (empty($item['customer_id'])) {
                    $item['customer_id'] = 'N/A';
                }
                if (empty($item['subscriber_id'])) {
                    $item['subscriber_id'] = 'N/A';
                }
                if (empty($item['subscriber_status'])) {
                    $item['subscriber_status'] = 'N/A';
                }
                if (empty($item['group_id'])) {
                    $item['group_id'] = 'N/A';
                }
            }
        }
        return $dataSource;
    }
}
