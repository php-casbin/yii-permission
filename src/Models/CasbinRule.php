<?php

namespace CasbinAdapter\Yii\Models;

use yii\db\ActiveRecord;

class CasbinRule extends ActiveRecord
{
    /**
     * @return string Active Record
     */
    public static function tableName()
    {
        return \Yii::$app->casbin->config['database']['casbin_rules_table'];
    }

    /**
     * getDb.
     *
     * @return yii\db\Connection
     */
    public static function getDb()
    {
        $db = \Yii::$app->casbin->config['database']['connection'] ?: 'db';

        return \Yii::$app->{$db};
    }

    public function rules()
    {
        return [
            [['ptype', 'v0'], 'required'],
            [['ptype', 'v0', 'v1', 'v2', 'v3', 'v4', 'v5'], 'safe'],
        ];
    }
}
