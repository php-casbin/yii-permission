<?php

namespace yii\permission\tests;

use Yii;
use PHPUnit\Framework\TestCase as BaseTestCase;
use yii\permission\models\CasbinRule;
use yii\web\Application;

class TestCase extends BaseTestCase
{
    protected $app;

    public function createApplication()
    {
        $config = require __DIR__ . '/../vendor/yiisoft/yii2-app-basic/config/web.php';
        $config['components']['permission'] = require __DIR__ . '/../config/permission.php';

        $config['components']['db']['dsn'] = 'mysql:host=' . $this->env('DB_HOST', '127.0.0.1') . ';port=' . $this->env('DB_PORT', '3306') . ';dbname=' . $this->env('DB_DATABASE', 'casbin');
        $config['components']['db']['username'] = $this->env('DB_USERNAME', 'root');
        $config['components']['db']['password'] = $this->env('DB_PASSWORD', '');

        return new Application($config);
    }

    /**
     * init table.
     */
    protected function initTable()
    {
        $db = CasbinRule::getDb();
        $tableName = CasbinRule::tableName();
        $table = $db->getTableSchema($tableName);
        if ($table) {
            $db->createCommand()->dropTable($tableName)->execute();
        }

        Yii::$app->permission->init();

        Yii::$app->db->createCommand()->batchInsert(
            $tableName,
            ['ptype', 'v0', 'v1', 'v2'],
            [
                ['p', 'alice', 'data1', 'read'],
                ['p', 'bob', 'data2', 'write'],
                ['p', 'data2_admin', 'data2', 'read'],
                ['p', 'data2_admin', 'data2', 'write'],
                ['g', 'alice', 'data2_admin', null],
            ]
        )->execute();
    }

    /**
     * Refresh the application instance.
     */
    protected function refreshApplication()
    {
        $this->app = $this->createApplication();
    }

    /**
     * This method is called before each test.
     */
    protected function setUp(): void/* The :void return type declaration that should be here would cause a BC issue */
    {
        if (!$this->app) {
            $this->refreshApplication();
        }

        $this->initTable();
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown(): void/* The :void return type declaration that should be here would cause a BC issue */ {}

    protected function env($key, $default = null)
    {
        $value = getenv($key);
        if (is_null($default)) {
            return $value;
        }

        return false === $value ? $default : $value;
    }
}
