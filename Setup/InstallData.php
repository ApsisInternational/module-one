<?php

namespace Apsis\One\Setup;

use Apsis\One\Model\Events\Historical;
use Apsis\One\Model\ResourceModel\Profile as ProfileResource;
use Apsis\One\Model\Service\Core as ApsisCoreHelper;
use Magento\Authorization\Model\Acl\Role\Group as RoleGroup;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\RoleFactory;
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
     * @var ApsisCoreHelper
     */
    private ApsisCoreHelper $apsisCoreHelper;

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
     * @var Historical
     */
    private Historical $historicalEvents;

    /**
     * @var State
     */
    private State $appState;

    /**
     * @param ProfileResource $profileResource
     * @param ApsisCoreHelper $apsisCoreHelper
     * @param Random $random
     * @param EncryptorInterface $encryptor
     * @param RoleFactory $roleFactory
     * @param RulesFactory $rulesFactory
     * @param Uninstall $uninstallSchema
     * @param Historical $historicalEvents
     * @param State $appState
     */
    public function __construct(
        ProfileResource $profileResource,
        ApsisCoreHelper $apsisCoreHelper,
        Random $random,
        EncryptorInterface $encryptor,
        RoleFactory $roleFactory,
        RulesFactory $rulesFactory,
        Uninstall $uninstallSchema,
        Historical $historicalEvents,
        State $appState
    ) {
        $this->appState = $appState;
        $this->historicalEvents = $historicalEvents;
        $this->profileResource = $profileResource;
        $this->apsisCoreHelper = $apsisCoreHelper;
        $this->random = $random;
        $this->encryptor = $encryptor;
        $this->roleFactory = $roleFactory;
        $this->rulesFactory = $rulesFactory;
        $this->uninstallSchema = $uninstallSchema;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);
            $this->appState->setAreaCode(Area::AREA_GLOBAL);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        try {
            $this->apsisCoreHelper->log(__METHOD__);

            $setup->startSetup();

            // Remove all module data from Magento tables
            $this->uninstallSchema->removeAllModuleDataFromMagentoTables($setup);

            //Create data in Magento tables
            $this->createDataInMagentoTables();

            // Identify Profiles
            $this->identifyAndMigrateProfiles($setup);

            // Find historical Events
            $this->historicalEvents->process($this->apsisCoreHelper);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }

        $setup->endSetup();
    }

    /**
     * @param ModuleDataSetupInterface $installer
     *
     * @return void
     */
    private function identifyAndMigrateProfiles(ModuleDataSetupInterface $installer): void
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);

            $apsisProfileTable = $installer->getTable(ApsisCoreHelper::APSIS_PROFILE_TABLE);
            $installer->getConnection()->truncateTable($apsisProfileTable);

            //Populate table with Customers and Subscribers
            $this->profileResource->populateProfilesTable($this->apsisCoreHelper);
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }

    /**
     * @return void
     */
    public function createDataInMagentoTables(): void
    {
        try {
            $this->apsisCoreHelper->log(__METHOD__);

            // Generate, encrypt and save 32 character long key
            $this->apsisCoreHelper->writer->save(
                ApsisCoreHelper::PATH_CONFIG_API_KEY,
                $this->encryptor->encrypt($this->random->getRandomString(32))
            );

            //Create Role for APSIS Support
            $role = $this->roleFactory->create()
                ->setRoleName('APSIS Support Agent')
                ->setUserType(UserContextInterface::USER_TYPE_ADMIN)
                ->setUserId(0)
                ->setRoleType(RoleGroup::ROLE_TYPE)
                ->setSortOrder(0)
                ->setTreeLevel(1)
                ->setParentId(0)
                ->save();

            $resource = [
                'Apsis_One::reports',
                'Apsis_One::profile',
                'Apsis_One::event',
                'Apsis_One::abandoned',
                'Apsis_One::logviewer',
                'Apsis_One::config',
            ];

            $this->rulesFactory->create()
                ->setRoleId($role->getId())
                ->setResources($resource)
                ->saveRel();
        } catch (Throwable $e) {
            $this->apsisCoreHelper->logError(__METHOD__, $e);
        }
    }
}
