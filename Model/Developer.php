<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\Abandoned;
use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\ResourceModel\Profile;
use Apsis\One\Model\ResourceModel\ProfileBatch;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;
use Exception;
use Magento\Config\Model\ResourceModel\Config as configResource;

class Developer
{
    /**
     * @var ApsisLogHelper
     */
    private $apsisLogHelper;

    /**
     * @var ProfileBatch
     */
    private $profileBatch;

    /**
     * @var Profile
     */
    private $profile;

    /**
     * @var Event
     */
    private $event;

    /**
     * @var Abandoned
     */
    private $abandoned;

    /**
     * @var configResource
     */
    private $configResource;

    /**
     * Developer constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param ProfileBatch $profileBatch
     * @param Profile $profile
     * @param Event $event
     * @param configResource $configResource
     * @param Abandoned $abandoned
     */
    public function __construct(
        ApsisLogHelper $apsisLogHelper,
        ProfileBatch $profileBatch,
        Profile $profile,
        Event $event,
        configResource $configResource,
        Abandoned $abandoned
    ) {
        $this->abandoned = $abandoned;
        $this->apsisLogHelper = $apsisLogHelper;
        $this->profileBatch = $profileBatch;
        $this->profile = $profile;
        $this->event = $event;
        $this->configResource = $configResource;
    }

    /**
     * @return bool
     */
    public function resetModule()
    {
        return (
            $this->profileBatch->truncateTable($this->apsisLogHelper) &&
            $this->event->truncateTable($this->apsisLogHelper) &&
            $this->abandoned->truncateTable($this->apsisLogHelper) &&
            $this->profile->truncateTable($this->apsisLogHelper) &&
            $this->profile->populateProfilesTable($this->apsisLogHelper) &&
            $this->deleteAllModuleConfig(
                sprintf("and path != '%s'", ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY)
            )
        );
    }

    /**
     * @param string $andCondition
     *
     * @return bool
     */
    public function deleteAllModuleConfig(string $andCondition = '')
    {
        try {
            $connection = $this->configResource->getConnection();
            $connection->delete($this->configResource->getMainTable(), "path like 'apsis_one%' $andCondition");
            $this->apsisLogHelper->cleanCache();
            return true;
        } catch (Exception $e) {
            $this->apsisLogHelper->logError(__METHOD__, $e);
            return false;
        }
    }
}
