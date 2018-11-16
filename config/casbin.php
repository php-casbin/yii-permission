<?php

return [
    'class' => '\CasbinAdapter\Yii\Casbin',
    /*
     * Yii-casbin model setting.
     */
    'model' => [
        // Available Settings: "file", "text"
        'config_type' => 'file',

        'config_file_path' => __DIR__.'/casbin-basic-model.conf',

        'config_text' => '',
    ],

    // Yii-casbin adapter .
    'adapter' => '\CasbinAdapter\Yii\Adapter',

    /*
     * Yii-casbin database setting.
     */
    'database' => [
        // Database connection for following tables.
        'connection' => '',

        // CasbinRule tables and model.
        'casbin_rules_table' => '{{%casbin_rule}}',
    ],
];
