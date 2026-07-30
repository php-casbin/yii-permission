<?php

namespace Yii\Permission\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Yii\Permission\AccessChecker;
use Casbin\Enforcer;
use Yii\Permission\Models\CasbinRule;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Connection\ConnectionInterface;
use Psr\Container\ContainerInterface;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Db\Migration\Migrator;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yii\Permission\Migrations\M240729000000CreateCasbinRuleTable;
use Composer\Factory;
use Composer\IO\NullIO;
use Yiisoft\Config\Composer\MergePlanProcess;

abstract class TestCase extends BaseTestCase
{
    public static ?Connection $dbConnection = null;
    protected ?ContainerInterface $container = null;
    protected ?Application $application = null;
    protected ?HttpApplicationRunner $runner = null;

    public function createApplication()
    {
        $rootPath = dirname(__DIR__);
        $appPath = $rootPath . '/vendor/yiisoft/app';

        // 1. Copy the dedicated test di configuration file directly to the app auto-scan path.
        copy($rootPath . '/tests/config/di.php', $appPath . '/config/common/di/permission.php');

        putenv('COMPOSER=' . $appPath . '/composer.json');

        $composer = Factory::create(new NullIO(), $appPath . '/composer.json');
        $composer->getConfig()->merge(['config' => ['vendor-dir' => $rootPath . '/vendor']]);
        new MergePlanProcess($composer);

        // 2. Instantiate native Runner, which automatically creates native container and Application.
        $this->runner = new HttpApplicationRunner(
            rootPath: $appPath,
            debug: true,
            checkEvents: false,
            diGroup: 'di',
            paramsGroup: 'params',
            vendorDirectory: '../../../vendor'
        );

        $this->container = $this->runner->getContainer();
        $this->application = $this->container->get(Application::class);

        return $this->application;
    }

    public function getEnforcer(): Enforcer
    {
        return $this->container->get(Enforcer::class);
    }

    public function getAccessChecker(): AccessChecker
    {
        return $this->container->get(AccessChecker::class);
    }

    protected function initTable()
    {
        $db = $this->container->get(ConnectionInterface::class);
        self::$dbConnection = $db;
        $ar = new CasbinRule($db, 'casbin_rule');
        $tableName = $ar->tableName();
        $schema = $db->getSchema();
        $table = $schema->getTableSchema($tableName);
        $migrator = new Migrator(
            $db,
            new NullMigrationInformer()
        );

        $migration = new M240729000000CreateCasbinRuleTable();

        if ($table !== null) {
            $migrator->down($migration);
        }

        $migrator->up($migration);

        $db->createCommand()->insertBatch(
            $tableName,
            [
                ['p', 'alice', 'data1', 'read'],
                ['p', 'bob', 'data2', 'write'],
                ['p', 'data2_admin', 'data2', 'read'],
                ['p', 'data2_admin', 'data2', 'write'],
                ['g', 'alice', 'data2_admin', null],
            ],
            ['ptype', 'v0', 'v1', 'v2']
        )->execute();
    }

    protected function refreshApplication()
    {
        $this->createApplication();
        $this->initTable();
    }

    protected function setUp(): void
    {
        $this->createApplication();
        $this->initTable();
    }

    protected function tearDown(): void
    {
    }
}
