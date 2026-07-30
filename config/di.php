<?php

declare(strict_types=1);

use Yii\Permission\AccessChecker;
use Yii\Permission\Adapter;
use Yii\Permission\Models\CasbinRule;
use Casbin\Enforcer;
use Casbin\Model\Model;
use Casbin\Log\Logger\DefaultLogger;
use Yiisoft\Db\Connection\ConnectionInterface;
use Psr\Container\ContainerInterface;

/** @var array $params */
$params = $params ?? (file_exists(__DIR__ . '/params.php') ? require __DIR__ . '/params.php' : []);

return [
    CasbinRule::class => static function (ContainerInterface $container) use ($params) {
        $config = $params['casbin/yii-permission'] ?? [];
        $db = null;

        $connection = $config['database']['connection'] ?? null;
        if (is_string($connection) && $container->has($connection)) {
            $db = $container->get($connection);
        } elseif ($connection instanceof ConnectionInterface) {
            $db = $connection;
        } elseif ($container->has(ConnectionInterface::class)) {
            $db = $container->get(ConnectionInterface::class);
        }

        $tableName = $config['database']['casbin_rules_table'] ?? '{{%casbin_rule}}';

        return new CasbinRule($db, $tableName);
    },

    Adapter::class => static function (ContainerInterface $container) {
        return new Adapter($container->get(CasbinRule::class));
    },

    Model::class => static function () use ($params) {
        $config = $params['casbin/yii-permission']['model'] ?? [];
        $model = new Model();

        if ('file' === ($config['config_type'] ?? 'file')) {
            $path = $config['config_file_path'] ?? (dirname(__DIR__) . '/config/casbin-basic-model.conf');
            $model->loadModel($path);
        } elseif ('text' === ($config['config_type'] ?? '')) {
            $model->loadModelFromText($config['config_text'] ?? '');
        }

        return $model;
    },

    Enforcer::class => static function (ContainerInterface $container) use ($params) {
        $config = $params['casbin/yii-permission'] ?? [];
        $model = $container->get(Model::class);
        $adapter = $container->get(Adapter::class);

        $logConfig = $config['log'] ?? [];
        $logger = $logConfig['logger'] ?? null;
        if (is_string($logger) && $container->has($logger)) {
            $logger = $container->get($logger);
        }

        $psrLogger = ($logger instanceof \Psr\Log\LoggerInterface) ? $logger : null;
        $casbinLogger = new DefaultLogger($psrLogger);
        $enableLog = (bool)($logConfig['enabled'] ?? true) && !is_null($logger);

        return new Enforcer($model, $adapter, $casbinLogger, $enableLog);
    },

    AccessChecker::class => static function (ContainerInterface $container) {
        return new AccessChecker($container->get(Enforcer::class));
    },
];
