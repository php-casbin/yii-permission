<?php

namespace yii\permission;

use yii\permission\models\CasbinRule;
use Casbin\Model\Model;
use Casbin\Persist\Adapter as AdapterContract;
use Casbin\Persist\BatchAdapter as BatchAdapterContract;
use Casbin\Persist\FilteredAdapter as FilteredAdapterContract;
use Casbin\Persist\UpdatableAdapter as UpdatableAdapterContract;
use Casbin\Persist\AdapterHelper;
use Casbin\Persist\Adapters\Filter;
use Casbin\Exceptions\InvalidFilterTypeException;

/**
 * DatabaseAdapter.
 *
 * @author techlee@qq.com
 */
class Adapter implements AdapterContract, BatchAdapterContract, FilteredAdapterContract, UpdatableAdapterContract
{
    use AdapterHelper;

    protected $casbinRule;

    /**
     * @var bool
     */
    private $filtered = false;

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
            throw $e;
        }
    }

    /**
     * @param string $sec
     * @param string $ptype
     * @param int $fieldIndex
     * @param string|null ...$fieldValues
     * @return array
     * @throws Throwable
     */
    public function _removeFilteredPolicy(string $sec, string $ptype, int $fieldIndex, ?string ...$fieldValues): array
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

        $removedRules = $this->casbinRule->find()->where($where)->all();
        $this->casbinRule->deleteAll($where);

        array_walk($removedRules, function (&$removedRule) {
            unset($removedRule->id);
            unset($removedRule->ptype);
            $removedRule = $removedRule->toArray();
            $removedRule = $this->filterRule($removedRule);
        });

        return $removedRules;
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
        $this->_removeFilteredPolicy($sec, $ptype, $fieldIndex, ...$fieldValues);
    }

    /**
     * Loads only policy rules that match the filter.
     *
     * @param Model $model
     * @param mixed $filter
     */
    public function loadFilteredPolicy(Model $model, $filter): void
    {
        $entity = clone $this->casbinRule;
        $entity = $entity->find();

        if (is_string($filter)) {
            $entity->where($filter);
        } elseif ($filter instanceof Filter) {
            foreach ($filter->p as $k => $v) {
                $where[$v] = $filter->g[$k];
                $entity->where([$v => $filter->g[$k]]);
            }
        } elseif ($filter instanceof \Closure) {
            $filter($entity);
        } else {
            throw new InvalidFilterTypeException('invalid filter type');
        }

        $rows = $entity->all();
        foreach ($rows as $row) {
            unset($row->id);
            $row = $row->toArray();
            $line = implode(', ', array_filter($row, function ($val) {
                return '' != $val && !is_null($val);
            }));
            $this->loadPolicyLine(trim($line), $model);
        }
        $this->setFiltered(true);
    }

    /**
     * Updates a policy rule from storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[] $oldRule
     * @param string[] $newPolicy
     */
    public function updatePolicy(string $sec, string $ptype, array $oldRule, array $newPolicy): void
    {
        $entity = clone $this->casbinRule;

        $condition['ptype'] = $ptype;
        foreach ($oldRule as $k => $v) {
            $condition['v' . $k] = $v;
        }
        $item = $entity->findOne($condition);
        foreach ($newPolicy as $k => $v) {
            $key = 'v' . $k;
            $item->$key = $v;
        }
        $item->update();
    }

    /**
     * UpdatePolicies updates some policy rules to storage, like db, redis.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[][] $oldRules
     * @param string[][] $newRules
     * @return void
     */
    public function updatePolicies(string $sec, string $ptype, array $oldRules, array $newRules): void
    {
        $transaction = $this->casbinRule->getDb()->beginTransaction();
        try {
            foreach ($oldRules as $i => $oldRule) {
                $this->updatePolicy($sec, $ptype, $oldRule, $newRules[$i]);
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * UpdateFilteredPolicies deletes old rules and adds new rules.
     *
     * @param string $sec
     * @param string $ptype
     * @param array $newPolicies
     * @param integer $fieldIndex
     * @param string ...$fieldValues
     * @return array
     */
    public function updateFilteredPolicies(string $sec, string $ptype, array $newRules, int $fieldIndex, ?string ...$fieldValues): array
    {
        $oldRules = [];
        $transaction = $this->casbinRule->getDb()->beginTransaction();
        try {
            $oldRules = $this->_removeFilteredPolicy($sec, $ptype, $fieldIndex, ...$fieldValues);
            $this->addPolicies($sec, $ptype, $newRules);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $oldRules;
    }

    /**
     * Filter the rule.
     *
     * @param array $rule
     * @return array
     */
    public function filterRule(array $rule): array
    {
        $rule = array_values($rule);

        $i = count($rule) - 1;
        for (; $i >= 0; $i--) {
            if ($rule[$i] != "" && !is_null($rule[$i])) {
                break;
            }
        }

        return array_slice($rule, 0, $i + 1);
    }

    /**
     * Returns true if the loaded policy has been filtered.
     *
     * @return bool
     */
    public function isFiltered(): bool
    {
        return $this->filtered;
    }

    /**
     * Sets filtered parameter.
     *
     * @param bool $filtered
     */
    public function setFiltered(bool $filtered): void
    {
        $this->filtered = $filtered;
    }
}
