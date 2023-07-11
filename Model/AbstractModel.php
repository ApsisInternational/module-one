<?php

namespace Apsis\One\Model;

use Apsis\One\Service\BaseService;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel as MagentoAbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\DB\Sql\ExpressionFactory;

abstract class AbstractModel extends MagentoAbstractModel
{
    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

    /**
     * @var ExpressionFactory
     */
    protected ExpressionFactory $expressionFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param DateTime $dateTime
     * @param ExpressionFactory $expressionFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DateTime $dateTime,
        ExpressionFactory $expressionFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
        $this->expressionFactory = $expressionFactory;
        $this->dateTime = $dateTime;
    }

    /**
     * @inerhitDoc
     */
    protected function _construct(): void
    {
        $this->_init(static::RESOURCE_MODEL);
    }

    /**
     * @param string $expressionString
     *
     * @return Expression
     */
    protected function getExpressionModel(string $expressionString): Expression
    {
        return $this->expressionFactory->create(['expression' => $expressionString]);
    }

    /**
     * @return $this
     */
    public function beforeSave(): static
    {
        parent::beforeSave();
        if (! $this instanceof ProfileModel && $this->isObjectNew()) {
            $this->setCreatedAt($this->dateTime->formatDate(true));

            if ($this instanceof WebhookModel) {
                $this->setSubscriptionId(BaseService::generateUniversallyUniqueIdentifier());
            }
        }

        if (! $this instanceof AbandonedModel) {
            $this->setUpdatedAt($this->dateTime->formatDate(true));
        }

        return $this;
    }
}
