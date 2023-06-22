<?php

namespace Apsis\One\Model\ResourceModel;

use Apsis\One\Service\BaseService;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Throwable;

abstract class AbstractResource extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(static::RESOURCE_MODEL, 'id');
    }

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @param Context $context
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(Context $context, DateTime $dateTime, $connectionName = null)
    {
        parent::__construct($context, $connectionName);
        $this->dateTime = $dateTime;
    }

    /**
     * @param array $items
     * @param BaseService $service
     *
     * @return int
     */
    public function insertMultipleItems(array $items, BaseService $service): int
    {
        try {
            return (int) $this->getConnection()->insertMultiple($this->getMainTable(), $items);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }

    /**
     * @param array $ids
     * @param array $bind
     * @param BaseService $service
     *
     * @return int
     */
    public function updateItemsByIds(array $ids, array $bind, BaseService $service): int
    {
        try {
            if (empty($ids)) {
                return 0;
            }

            if (! $this instanceof AbandonedResource) {
                $bind['updated_at'] = $this->dateTime->formatDate(true);
            }

            return (int) $this->getConnection()
                ->update($this->getMainTable(), $bind, ['id IN (?)' => $ids]);
        } catch (Throwable $e) {
            $service->logError(__METHOD__, $e);
            return 0;
        }
    }
}
