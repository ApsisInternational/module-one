<?php

namespace Apsis\One\Model;

use Apsis\One\Model\Events\Historical;
use Apsis\One\Model\ResourceModel\Abandoned;
use Apsis\One\Model\ResourceModel\Event;
use Apsis\One\Model\ResourceModel\Profile;
use Apsis\One\Model\ResourceModel\ProfileBatch;
use Apsis\One\Model\Service\Config as ApsisConfigHelper;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Apsis\One\Model\Profile as ApsisProfile;
use Throwable;

class Developer
{
    /**
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisHelper;

    /**
     * @var ProfileBatch
     */
    private ProfileBatch $profileBatch;

    /**
     * @var Profile
     */
    private Profile $profile;

    /**
     * @var Event
     */
    private Event $event;

    /**
     * @var Abandoned
     */
    private Abandoned $abandoned;

    /**
     * @var Historical
     */
    private Historical $historicalEvents;

    /**
     * Developer constructor.
     *
     * @param ApsisCoreHelper $apsisHelper
     * @param ProfileBatch $profileBatch
     * @param Profile $profile
     * @param Event $event
     * @param Historical $historicalEvents
     * @param Abandoned $abandoned
     */
    public function __construct(
        ApsisCoreHelper $apsisHelper,
        ProfileBatch $profileBatch,
        Profile $profile,
        Event $event,
        Historical $historicalEvents,
        Abandoned $abandoned
    ) {
        $this->historicalEvents = $historicalEvents;
        $this->abandoned = $abandoned;
        $this->apsisHelper = $apsisHelper;
        $this->profileBatch = $profileBatch;
        $this->profile = $profile;
        $this->event = $event;
    }

    /**
     * @return bool
     */
    public function resetModule(): bool
    {
        try {
            $this->apsisHelper->log('Module full reset is requested from "RESET" button.');

            $truncateStatus = $this->truncateAllTables();
            if ($truncateStatus) {
                $this->apsisHelper->log('All tables truncated');
            } else {
                $this->apsisHelper->log('Unable to truncate some tables.');
            }

            $configStatus = $this->profile->deleteAllModuleConfig(
                $this->apsisHelper,
                sprintf("AND path != '%s'", ApsisConfigHelper::SYNC_SETTING_SUBSCRIBER_ENDPOINT_KEY)
            );
            if ($configStatus) {
                $this->apsisHelper->log('All configs other then key deleted.');
            } else {
                $this->apsisHelper->log('Unable to delete some configurations.');
            }

            $populateStatus = $this->profile->populateProfilesTable($this->apsisHelper);
            //Set status to 5 for each Profile type (for all Profiles) if given Profile type has is_[PROFILE_TYPE] = 0
            $this->profile->resetProfiles(
                $this->apsisHelper,
                [],
                [],
                ApsisProfile::SYNC_STATUS_NA,
                ['condition' => 'is_', 'value' => ApsisProfile::NO_FLAG]
            );
            if ($populateStatus) {
                $this->apsisHelper->log('Profile table is populated with customers and subscribers.');
            } else {
                $this->apsisHelper->log('Unable to complete populate Profile table action.');
            }

            //Fetch historical events
            $this->historicalEvents->process($this->apsisHelper);
            $this->apsisHelper->log('Historical events are fetched for all stores.');

            if ($configStatus && $truncateStatus && $populateStatus) {
                $this->apsisHelper->log('Module full reset request is complete.');
                return true;
            } else {
                $this->apsisHelper->log('Unable to perform some actions from full reset request.');
                return false;
            }
        } catch (Throwable $e) {
            $this->apsisHelper->logError(__METHOD__, $e);
            return false;
        }
    }

    /**
     * @return bool
     */
    private function truncateAllTables(): bool
    {
        return (
            $this->profile->truncateTable($this->apsisHelper) &&
            $this->profileBatch->truncateTable($this->apsisHelper) &&
            $this->event->truncateTable($this->apsisHelper) &&
            $this->abandoned->truncateTable($this->apsisHelper)
        );
    }
}
