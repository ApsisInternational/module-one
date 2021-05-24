<?php

namespace Apsis\One\Model;

use Apsis\One\Model\ResourceModel\Abandoned;
use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\ResourceModel\Profile;
use Apsis\One\Model\ResourceModel\ProfileBatch;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Log as ApsisLogHelper;

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
     * Developer constructor.
     *
     * @param ApsisLogHelper $apsisLogHelper
     * @param ProfileBatch $profileBatch
     * @param Profile $profile
     * @param Event $event
     * @param Abandoned $abandoned
     */
    public function __construct(
        ApsisLogHelper $apsisLogHelper,
        ProfileBatch $profileBatch,
        Profile $profile,
        Event $event,
        Abandoned $abandoned
    ) {
        $this->abandoned = $abandoned;
        $this->apsisLogHelper = $apsisLogHelper;
        $this->profileBatch = $profileBatch;
        $this->profile = $profile;
        $this->event = $event;
    }

    /**
     * @return bool
     */
    public function resetModule()
    {
        $this->apsisLogHelper->log('Module full reset is performed.');
        return (
            $this->profileBatch->truncateTable($this->apsisLogHelper) &&
            $this->event->truncateTable($this->apsisLogHelper) &&
            $this->abandoned->truncateTable($this->apsisLogHelper) &&
            $this->profile->truncateTable($this->apsisLogHelper) &&
            $this->profile->populateProfilesTable($this->apsisLogHelper) &&
            $this->profile->deleteAllModuleConfig(
                $this->apsisLogHelper,
                sprintf("AND path != '%s'", ApsisConfigHelper::CONFIG_APSIS_ONE_SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY)
            )
        );
    }
}
