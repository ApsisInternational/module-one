<?php

namespace Apsis\One\Setup;

use Apsis\One\Service\Data;
use Apsis\One\Model\ResourceModel\ProfileResource;
use Apsis\One\Service\BaseService;
use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\Rules;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\Role;
use Magento\Authorization\Model\ResourceModel\Role as RoleResource;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Throwable;

class InstallData implements InstallDataInterface
{
    /**
     * @var ProfileResource
     */
    private ProfileResource $profileResource;

    /**
     * @var BaseService
     */
    private BaseService $baseService;

    /**
     * @var Random
     */
    private Random $random;

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @var RoleFactory
     */
    private RoleFactory $roleFactory;

    /**
     * @var Uninstall
     */
    private Uninstall $uninstallSchema;

    /**
     * @var RulesFactory
     */
    private RulesFactory $rulesFactory;

    /**
     * @var Data\HistoricalEvents
     */
    private Data\HistoricalEvents $historicalEvents;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @var RoleResource
     */
    private RoleResource $roleResource;

    /**
     * @param ProfileResource $profileResource
     * @param BaseService $baseService
     * @param Random $random
     * @param EncryptorInterface $encryptor
     * @param RoleFactory $roleFactory
     * @param RulesFactory $rulesFactory
     * @param Uninstall $uninstallSchema
     * @param Data\HistoricalEvents $historicalEvents
     * @param State $appState
     * @param RoleResource $roleResource
     */
    public function __construct(
        ProfileResource $profileResource,
        BaseService $baseService,
        Random $random,
        EncryptorInterface $encryptor,
        RoleFactory $roleFactory,
        RulesFactory $rulesFactory,
        Uninstall $uninstallSchema,
        Data\HistoricalEvents $historicalEvents,
        State $appState,
        RoleResource $roleResource
    ) {
        $this->appState = $appState;
        $this->historicalEvents = $historicalEvents;
        $this->profileResource = $profileResource;
        $this->baseService = $baseService;
        $this->random = $random;
        $this->encryptor = $encryptor;
        $this->roleFactory = $roleFactory;
        $this->rulesFactory = $rulesFactory;
        $this->uninstallSchema = $uninstallSchema;
        $this->roleResource = $roleResource;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context): void
    {
        $this->baseService->log(__METHOD__);
        $startTime = microtime(true);
        $startMemory = memory_get_peak_usage();

        try {
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }

        try {
            $setup->startSetup();

            // Remove all module data from Magento tables
            $this->uninstallSchema->removeAllModuleDataFromMagentoTables($setup);

            //Create data in Magento tables
            $this->createDataInMagentoTables();

            // Identify Profiles
            $this->identifyAndMigrateProfiles($setup);

            // Find historical Events
            $this->historicalEvents->identifyAndFetchHistoricalEvents($this->baseService);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
        $setup->endSetup();
        $this->baseService->logPerformanceData(__METHOD__, $startTime, $startMemory);
    }

    /**
     * @param ModuleDataSetupInterface $installer
     *
     * @return void
     */
    private function identifyAndMigrateProfiles(ModuleDataSetupInterface $installer): void
    {
        try {
            $this->baseService->log(__METHOD__);

            $apsisProfileTable = $installer->getTable(BaseService::APSIS_PROFILE_TABLE);
            $installer->getConnection()->truncateTable($apsisProfileTable);

            //Populate table with Customers and Subscribers
            $this->profileResource->populateProfilesTable($this->baseService);
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
    }

    /**
     * @return Role
     */
    private function getRoleModel(): Role
    {
        return $this->roleFactory->create();
    }

    /**
     * @return Rules
     */
    private function getRulesModel(): Rules
    {
        return $this->rulesFactory->create();
    }

    /**
     * @return void
     */
    public function createDataInMagentoTables(): void
    {
        try {
            $this->baseService->log(__METHOD__);

            // Generate, encrypt and save 32 character long key
            $this->baseService->saveDefaultConfig(
                BaseService::PATH_CONFIG_API_KEY,
                $this->encryptor->encrypt($this->random->getRandomString(32))
            );

            //Create Role for APSIS Support
            $role = $this->getRoleModel()
                ->setRoleName('APSIS Support Agent')
                ->setUserType(UserContextInterface::USER_TYPE_ADMIN)
                ->setUserId(0)
                ->setRoleType(RoleGroup::ROLE_TYPE)
                ->setSortOrder(0)
                ->setTreeLevel(1)
                ->setParentId(0);
            $this->roleResource->save($role);

            $resource = [
                'Apsis_One::reports',
                'Apsis_One::profile',
                'Apsis_One::event',
                'Apsis_One::abandoned',
                'Apsis_One::logviewer',
                'Apsis_One::config',
            ];

            $this->getRulesModel()
                ->setRoleId($role->getId())
                ->setResources($resource)
                ->saveRel();
        } catch (Throwable $e) {
            $this->baseService->logError(__METHOD__, $e);
        }
    }
}
