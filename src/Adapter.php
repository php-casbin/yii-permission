<?php

namespace yii\permission;

use yii\permission\models\CasbinRule;
use Casbin\Model\Model;
use Casbin\Persist\Adapter as AdapterContract;
use Casbin\Persist\BatchAdapter as BatchAdapterContract;
use Casbin\Persist\AdapterHelper;

/**
 * DatabaseAdapter.
 *
 * @author techlee@qq.com
 */
class Adapter implements AdapterContract, BatchAdapterContract
{
    use AdapterHelper;

    protected $casbinRule;

    public function __construct(CasbinRule $casbinRule)
    {
        $this->casbinRule = $casbinRule;
    }

    public function savePolicyLine($ptype, array $rule)
    {
        $col['ptype'] = $ptype;
        foreach ($rule as $key => $value) {
            $col['v' . strval($key) . ''] = $value;
        }
        $ar = clone $this->casbinRule;
        $ar->setAttributes($col);
        $ar->save();
    }

    /**
     * loads all policy rules from the storage.
     *
     * @param Model $model
     */
    public function loadPolicy(Model $model): void
    {
        $ar = clone $this->casbinRule;
        $rows = $ar->find()->all();

        foreach ($rows as $row) {
            $line = implode(', ', array_filter(array_slice($row->toArray(), 1), function ($val) {
                return '' != $val && !is_null($val);
            }));
            $this->loadPolicyLine(trim($line), $model);
        }
    }

    /**
     * saves all policy rules to the storage.
     *
     * @param Model $model
     */
    public function savePolicy(Model $model): void
    {
        foreach ($model['p'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }

        foreach ($model['g'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }
    }

    /**
     * adds a policy rule to the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param array  $rule
     */
    public function addPolicy(string $sec, string $ptype, array $rule): void
    {
        $this->savePolicyLine($ptype, $rule);
    }

    /**
     * Adds a policy rules to the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[][] $rules
     */
    public function addPolicies(string $sec, string $ptype, array $rules): void
    {
        $rows = [];
        $columns = array_keys($rules[0]);
        array_walk($columns, function (&$item) {
            $item = 'v' . strval($item);
        });
        array_unshift($columns, 'ptype');

        foreach ($rules as $rule) {
            $temp['`ptype`'] = $ptype;
            foreach ($rule as $key => $value) {
                $temp['`v'. strval($key) . '`'] = $value;
            }
            $rows[] = $temp;
            $temp = [];
        }

        $command = $this->casbinRule->getDb()->createCommand();
        $tableName = $this->casbinRule->tableName();
        $command->batchInsert($tableName, $columns, $rows)->execute();
    }

    /**
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param array  $rule
     */
    public function removePolicy(string $sec, string $ptype, array $rule): void
    {
        $where = [];
        $where['ptype'] = $ptype;

        foreach ($rule as $key => $value) {
            $where['v' . strval($key)] = $value;
        }

        $this->casbinRule->deleteAll($where);
    }

    /**
     * Removes policy rules from the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[][] $rules
     */
    public function removePolicies(string $sec, string $ptype, array $rules): void
    {
        $transaction = $this->casbinRule->getDb()->beginTransaction();
        try {
            foreach ($rules as $rule) {
                $this->removePolicy($sec, $ptype, $rule);
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
        }
    }

    /**
     * RemoveFilteredPolicy removes policy rules that match the filter from the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param int    $fieldIndex
     * @param string ...$fieldValues
     */
    public function removeFilteredPolicy(string $sec, string $ptype, int $fieldIndex, string ...$fieldValues): void
    {
        $where = [];
        $where['ptype'] = $ptype;

        foreach (range(0, 5) as $value) {
            if ($fieldIndex <= $value && $value < $fieldIndex + count($fieldValues)) {
                if ('' != $fieldValues[$value - $fieldIndex]) {
                    $where['v' . strval($value)] = $fieldValues[$value - $fieldIndex];
                }
            }
        }

        $this->casbinRule->deleteAll($where);
    }
}
