<?php

namespace yii\permission\components;

use Yii;
use yii\base\Component;
use yii\web\User;

class PermissionPolicy extends Component
{
    /**
     * @var bool whether this is an 'allow' rule or 'deny' rule.
     */
    public $allow = false;

    /**
     * @var array|null list of the controller IDs that this rule applies to.
     */
    public $actions = [];

    /**
     * @var array|null list of params that passed to Casbin.
     */
    public $enforce = [];

    /**
     * @var callable|null a callback that will be called if the access should be denied
     */
    public $denyCallback;

    /**
     * Checks whether the given action is allowed for the specified user.
     *
     * @param string $action the action to be checked
     * @param User $user the user to be checked
     * 
     * @return bool|null true if the action is allowed, false if not, null if the rule does not apply
     */
    public function allows($action, $user)
    {
        if (
            $this->matchAction($action)
            && $this->matchEnforce($user, $this->enforce)
        ) {
            return $this->allow ? true : false;
        }

        return null;
    }

    /**
     * Checks if the rule applies to the specified action.
     * 
     * @param Action $action the action
     * @return bool whether the rule applies to the action
     */
    protected function matchAction($action)
    {
        return empty($this->actions) || in_array($action->id, $this->actions, true);
    }

    /**
     * Checks if the rule applies to the specified user.
     * 
     * @param User $user
     * @param array $params
     * 
     * @return bool
     */
    protected function matchEnforce($user, $params)
    {
        return Yii::$app->permission->enforce($user->getId(), ...$params);
    }
}
