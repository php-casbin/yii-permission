<?php

namespace yii\casbin;

use Casbin\Exceptions\CasbinException;
use Casbin\Persist\Adapter as AdapterContract;
use Casbin\Persist\AdapterHelper;
use yii\helpers\Yii;

class Adapter implements AdapterContract
{
    use AdapterHelper;

    private $_db;
    const TABLE = '{{%casbin_rule}}';

    public function __construct($db)
    {
        /** @var \yii\web\Application $app */
        $app = Yii::get('app');
        $this->_db = $db;
    }

    public function savePolicyLine($ptype, array $rule)
    {
        $col['ptype'] = $ptype;
        foreach ($rule as $key => $value) {
            $col['v' . strval($key) . ''] = $value;
        }
        $this->_db->createCommand()->insert(self::TABLE, $col)->execute();
    }

    public function loadPolicy($model)
    {
        $table = self::TABLE;
        $rows = $this->_db->createCommand("SELECT * FROM {$table}")->queryAll();
        foreach ($rows as $row) {
            $line = implode(', ', array_slice(array_values($row), 1));
            $this->loadPolicyLine(trim($line), $model);
        }
    }

    public function savePolicy($model)
    {
        foreach ($model->model['p'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }
        foreach ($model->model['g'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }
        return true;
    }

    public function addPolicy($sec, $ptype, $rule)
    {
        return $this->savePolicyLine($ptype, $rule);
    }

    public function removePolicy($sec, $ptype, $rule)
    {
        $wheres = [
            'ptype' => $ptype
        ];
        foreach ($rule as $key => $value) {
            $wheres['v' . strval($key)] = $value;
        }
        return $this->_db->createCommand()->delete(self::TABLE, [
            $wheres
        ])->execute();
    }

    public function removeFilteredPolicy($sec, $ptype, $fieldIndex, ...$fieldValues)
    {
        throw new CasbinException('not implemented');
    }
}
