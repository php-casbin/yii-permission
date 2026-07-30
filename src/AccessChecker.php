<?php

namespace Yii\Permission;

use Casbin\Enforcer;
use Yiisoft\Access\AccessCheckerInterface;

/**
 * AccessChecker component for Yii3 framework.
 * Integrates Casbin Enforcer with Yii3 AccessCheckerInterface specification.
 *
 * @author leeqvip@gmail.com
 */
class AccessChecker implements AccessCheckerInterface
{
    private Enforcer $enforcer;

    /**
     * Constructor.
     *
     * @param Enforcer $enforcer
     */
    public function __construct(Enforcer $enforcer)
    {
        $this->enforcer = $enforcer;
    }

    /**
     * Checks if the user has permission to perform the given action according to Yii3 AccessCheckerInterface specification.
     *
     * @param mixed $userId The ID of the user.
     * @param string $permissionName Permission name or policy string (e.g. 'data1,read' or 'data1').
     * @param array $parameters Additional parameters passed to Casbin enforce.
     *
     * @return bool Whether access is granted.
     */
    public function userHasPermission(mixed $userId, string $permissionName, array $parameters = []): bool
    {
        if (str_contains($permissionName, ',')) {
            $params = explode(',', $permissionName);
        } else {
            $params = [$permissionName];
        }

        return $this->enforcer->enforce($userId, ...array_merge($params, $parameters));
    }


}
