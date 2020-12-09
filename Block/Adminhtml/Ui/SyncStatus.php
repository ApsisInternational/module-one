<?php

namespace Apsis\One\Block\Adminhtml\Ui;

use Magento\Ui\Component\Listing\Columns\Column;

class SyncStatus extends Column
{
    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if ($item['is_subscriber'] == '0') {
                    $item['subscriber_sync_status'] = 'NA';
                }
                if ($item['is_customer'] == '0') {
                    $item['customer_sync_status'] = 'NA';
                }
            }
        }

        return $dataSource;
    }
}
