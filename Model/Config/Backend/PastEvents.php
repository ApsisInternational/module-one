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
        if ($this->getValue()) {
            $this->writer->save(
                ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DURATION_TIMESTAMP,
                $this->dateTime->formatDate(true),
                $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeCode()
            );
        } else {
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
            ApsisConfigHelper::CONFIG_APSIS_ONE_EVENTS_HISTORICAL_EVENTS_DURATION_TIMESTAMP,
            $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->getScopeCode()
        );
    }
}
