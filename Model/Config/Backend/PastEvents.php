<?php

namespace Apsis\One\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime;

class PastEvents extends Value
{
    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var WriterInterface
     */
    private $writer;

    const TYPE_DURATION_TO_TIMESTAMP_MAPPING = [
        ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_EVENTS_DURATION =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_ORDER_DURATION_TIMESTAMP,
        ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_EVENTS_DURATION =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_CART_DURATION_TIMESTAMP,
        ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_EVENTS_DURATION =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_REVIEW_DURATION_TIMESTAMP,
        ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_EVENTS_DURATION =>
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_WISHLIST_DURATION_TIMESTAMP
    ];

    /**
     * PastEvents constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param DateTime $dateTime
     * @param WriterInterface $writer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        DateTime $dateTime,
        WriterInterface $writer,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        $this->dateTime = $dateTime;
        $this->writer = $writer;
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
        if ((boolean) $this->getValue() && $this->isValueChanged()) {
            $this->writer->save(
                self::TYPE_DURATION_TO_TIMESTAMP_MAPPING[$this->getPath()],
                $this->dateTime->formatDate(true),
                $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeCode()
            );
        } elseif (! (boolean) $this->getValue()) {
            $this->deleteDependantConfig();
        }
        return parent::afterSave();
    }

    /**
     * @return Value
     */
    public function afterDelete()
    {
        $this->deleteDependantConfig();
        return parent::afterDelete();
    }

    /**
     * return void
     */
    private function deleteDependantConfig()
    {
        $this->writer->delete(
            self::TYPE_DURATION_TO_TIMESTAMP_MAPPING[$this->getPath()],
            $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->getScopeCode()
        );
    }
}
