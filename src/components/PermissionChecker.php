<?php

namespace yii\permission\components;

use Yii;
use yii\rbac\CheckAccessInterface;

class PermissionChecker implements CheckAccessInterface
{
    /**
     * Checks if the user has access to a certain policy.
     *
     * @param int $userId The ID of the user to check.
     * @param string $policy The policy to check access for.
     * @param array $guards Optional guards to check, not supported yet.
     *
     * @return bool Whether the user has access to the policy.
     */
    public function checkAccess($userId, $policy, $guards = [])
    {
        $params = explode(',', $policy);
        return Yii::$app->permission->enforce($userId, ...$params);
    }
}
