<?php

namespace Yii\Permission;

use Yii\Permission\Models\CasbinRule;
use Casbin\Model\Model;
use Casbin\Persist\Adapter as AdapterContract;
use Casbin\Persist\BatchAdapter as BatchAdapterContract;
use Casbin\Persist\FilteredAdapter as FilteredAdapterContract;
use Casbin\Persist\UpdatableAdapter as UpdatableAdapterContract;
use Casbin\Persist\AdapterHelper;
use Casbin\Persist\Adapters\Filter;
use Casbin\Exceptions\InvalidFilterTypeException;

/**
 * DatabaseAdapter for Yii3.
 *
 * @author leeqvip@gmail.com
 */
class Adapter implements AdapterContract, BatchAdapterContract, FilteredAdapterContract, UpdatableAdapterContract
{
    use AdapterHelper;

    protected CasbinRule $casbinRule;

    /**
     * @var bool
     */
    private bool $filtered = false;

    public function __construct(CasbinRule $casbinRule)
    {
        $this->casbinRule = $casbinRule;
    }

    public function savePolicyLine(string $ptype, array $rule): void
    {
        $col['ptype'] = $ptype;
        foreach ($rule as $key => $value) {
            $col['v' . $key] = $value;
        }
        $this->casbinRule->db()->createCommand()->insert($this->casbinRule->tableName(), $col)->execute();
    }

    /**
     * loads all policy rules from the storage.
     *
     * @param Model $model
     */
    public function loadPolicy(Model $model): void
    {
        $rows = $this->casbinRule->createQuery()->all();

        foreach ($rows as $row) {
            $line = implode(', ', array_filter(array_slice($row->propertyValues(), 1), function ($val) {
                return '' !== $val && !is_null($val);
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
        if (empty($rules)) {
            return;
        }

        $columns = array_keys($rules[0]);
        array_walk($columns, function (&$item) {
            $item = 'v' . strval($item);
        });
        array_unshift($columns, 'ptype');

        $rows = [];
        foreach ($rules as $rule) {
            $temp = [$ptype];
            foreach ($rule as $value) {
                $temp[] = $value;
            }
            $rows[] = $temp;
        }

        $this->casbinRule->db()->createCommand()->insertBatch($this->casbinRule->tableName(), $rows, $columns)->execute();
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
        $this->casbinRule->db()->transaction(function () use ($sec, $ptype, $rules) {
            foreach ($rules as $rule) {
                $this->removePolicy($sec, $ptype, $rule);
            }
        });
    }

    /**
     * @param string $sec
     * @param string $ptype
     * @param int $fieldIndex
     * @param string|null ...$fieldValues
     * @return array
     * @throws \Throwable
     */
    public function _removeFilteredPolicy(string $sec, string $ptype, int $fieldIndex, ?string ...$fieldValues): array
    {
        $where = [];
        $where['ptype'] = $ptype;

        foreach (range(0, 5) as $value) {
            if ($fieldIndex <= $value && $value < $fieldIndex + count($fieldValues)) {
                if ('' !== $fieldValues[$value - $fieldIndex]) {
                    $where['v' . strval($value)] = $fieldValues[$value - $fieldIndex];
                }
            }
        }

        $removedRules = $this->casbinRule->createQuery()->where($where)->all();
        $this->casbinRule->deleteAll($where);

        $result = [];
        foreach ($removedRules as $removedRule) {
            $ruleArray = $removedRule->propertyValues();
            unset($ruleArray['id'], $ruleArray['ptype']);
            $result[] = $this->filterRule($ruleArray);
        }

        return $result;
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
        $query = $this->casbinRule->createQuery();

        if (is_string($filter)) {
            $query->where($filter);
        } elseif ($filter instanceof Filter) {
            $where = [];
            foreach ($filter->p as $k => $v) {
                $where[$v] = $filter->g[$k];
            }
            $query->where($where);
        } elseif ($filter instanceof \Closure) {
            $filter($query);
        } else {
            throw new InvalidFilterTypeException('invalid filter type');
        }

        $rows = $query->all();
        foreach ($rows as $row) {
            $rowArray = $row->propertyValues();
            unset($rowArray['id']);
            $line = implode(', ', array_filter($rowArray, function ($val) {
                return '' !== $val && !is_null($val);
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
        $condition['ptype'] = $ptype;
        foreach ($oldRule as $k => $v) {
            $condition['v' . $k] = $v;
        }
        $updateData = [];
        foreach ($newPolicy as $k => $v) {
            $updateData['v' . $k] = $v;
        }
        $this->casbinRule->db()->createCommand()->update($this->casbinRule->tableName(), $updateData, $condition)->execute();
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
        $this->casbinRule->db()->transaction(function () use ($sec, $ptype, $oldRules, $newRules) {
            foreach ($oldRules as $i => $oldRule) {
                $this->updatePolicy($sec, $ptype, $oldRule, $newRules[$i]);
            }
        });
    }

    /**
     * UpdateFilteredPolicies deletes old rules and adds new rules.
     *
     * @param string $sec
     * @param string $ptype
     * @param array $newRules
     * @param integer $fieldIndex
     * @param string ...$fieldValues
     * @return array
     */
    public function updateFilteredPolicies(string $sec, string $ptype, array $newRules, int $fieldIndex, ?string ...$fieldValues): array
    {
        return $this->casbinRule->db()->transaction(function () use ($sec, $ptype, $newRules, $fieldIndex, $fieldValues) {
            $oldRules = $this->_removeFilteredPolicy($sec, $ptype, $fieldIndex, ...$fieldValues);
            $this->addPolicies($sec, $ptype, $newRules);
            return $oldRules;
        });
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
            if ($rule[$i] !== "" && !is_null($rule[$i])) {
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
