<?php

namespace Apsis\One\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection as MagentoAbstractCollection;
use Apsis\One\Model\AbstractModel;
use Magento\Framework\Data\Collection;

abstract class AbstractCollection extends MagentoAbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_idFieldName = 'id';
        $this->_init(static::MODEL, static::RESOURCE_MODEL);
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return $this
     */
    public function filterByField(string $field, mixed $value): static
    {
        if (is_array($value)) {
            $this->addFieldToFilter($field, ['in' => $value]);
        } elseif (is_scalar($value)) {
            $this->addFieldToFilter($field, $value);
        }
        return $this;
    }

    /**
     * @param array|string $field
     * @param mixed $value
     * @param int $limit
     *
     * @return $this
     */
    public function getCollection(array|string $field, mixed $value = null, int $limit = 0): static
    {
        $this->addFieldToSelect('*');

        if (is_array($field)) {
            foreach ($field as $column => $value) {
                $this->filterByField($column, $value);
            }
        } elseif ($value) {
            $this->filterByField($field, $value);
        }

        if ($limit > 0) {
            $this->setPageSize($limit);
        }

        return $this;
    }

    /**
     * @param int $page
     * @param int $pageSize
     * @param string $field
     *
     * @return $this
     */
    public function setPaginationOnCollection(int $page, int $pageSize, string $field): static
    {
        $this->setOrder($field, Collection::SORT_ORDER_ASC);
        $this->getSelect()->limitPage($page, $pageSize);
        return $this;
    }

    /**
     * @param string|array $field
     * @param mixed $value
     *
     * @return AbstractModel|bool
     */
    public function getFirstItemFromCollection(string|array $field, mixed $value = null): bool|AbstractModel
    {
        $item = false;
        $collection = $this->getCollection($field, $value, 1);
        if ($collection->getSize()) {
            /** @var AbstractModel $item */
            $item = $collection->getFirstItem();
        }
        return $item;
    }
}
