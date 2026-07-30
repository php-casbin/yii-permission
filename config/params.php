<?php

declare(strict_types=1);

return [
    'casbin/yii-permission' => [
        /*
         * Yii-casbin model setting.
         */
        'model' => [
            // Available Settings: "file", "text"
            'config_type' => 'file',

            'config_file_path' => dirname(__DIR__) . '/config/casbin-basic-model.conf',

            'config_text' => '',
        ],

        /*
         * Yii-casbin logger.
         */
        'log' => [
            // changes whether YiiPermission will log messages to the Logger.
            'enabled' => false,
            // Casbin Logger, Supported: \Psr\Log\LoggerInterface|string
            'logger' => 'log',
        ],

        /*
         * Yii-casbin adapter.
         */
        'adapter' => \Yii\Permission\Adapter::class,

        /*
         * Yii-casbin database setting.
         */
        'database' => [
            // Database connection for following tables.
            'connection' => null,

            // CasbinRule tables and model.
            'casbin_rules_table' => '{{%casbin_rule}}',
        ],
    ],
];
