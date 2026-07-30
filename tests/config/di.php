<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequestFactory;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Driver;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Cache\NullCache;
use Yii\Permission\Tests\TestCase;

// After being copied to vendor/yiisoft/app/config/common/di/permission.php, 6 levels up is the project root directory.
$projectRoot = dirname(__DIR__, 6);

$params = file_exists($projectRoot . '/config/params.php')
    ? require $projectRoot . '/config/params.php'
    : [];

$myDiConfig = require $projectRoot . '/config/di.php';

return [
    ...$myDiConfig,
    ResponseFactoryInterface::class => ResponseFactory::class,
    ServerRequestFactoryInterface::class => ServerRequestFactory::class,
    ConnectionInterface::class => static function () {
        if (TestCase::$dbConnection !== null) {
            return TestCase::$dbConnection;
        }
        $driver = new Driver('sqlite::memory:');
        $db = new Connection($driver, new SchemaCache(new NullCache()));
        $db->open();
        TestCase::$dbConnection = $db;
        return $db;
    },
];
