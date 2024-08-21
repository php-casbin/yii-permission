<?php

namespace yii\permission\components;

use Yii;
use yii\base\ActionFilter;
use yii\di\Instance;
use yii\web\ForbiddenHttpException;
use yii\web\User;

class PermissionControl extends ActionFilter
{
    /**
     * @var User|array|string|false the user object.
     */
    public $user = 'user';

    /**
     * @var callable|null a callback that will be called if the access should be denied
     */
    public $denyCallback;

    /**
     * @var array the default configuration of the policy
     */
    public $policyConfig = ['class' => 'yii\permission\components\PermissionPolicy'];

    /**
     * @var array the policies.
     */
    public $policy = [];

    /**
     * Initializes the PermissionControl component.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        if ($this->user !== false) {
            $this->user = Instance::ensure($this->user, User::class);
        }
        foreach ($this->policy as $i => $policy) {
            if (is_array($policy)) {
                $this->policy[$i] = Yii::createObject(array_merge($this->policyConfig, $policy));
            }
        }
    }

    /**
     * Checks if the current user has permission to perform the given action.
     *
     * @param Action $action the action to be performed
     * @throws ForbiddenHttpException if the user does not have permission
     * @return bool true if the user has permission, false otherwise
     */
    public function beforeAction($action)
    {
        $user = $this->user;
        foreach ($this->policy as $policy) {
            if ($allow = $policy->allows($action, $user)) {
                return true;
            } elseif ($allow === false) {
                if (isset($policy->denyCallback)) {
                    call_user_func($policy->denyCallback, $policy, $action);
                } elseif ($this->denyCallback !== null) {
                    call_user_func($this->denyCallback, $policy, $action);
                } else {
                    $this->denyAccess($user);
                }

                return false;
            }
        }

        if ($this->denyCallback !== null) {
            call_user_func($this->denyCallback, null, $action);
        } else {
            $this->denyAccess($user);
        }
        return false;
    }
    /**
     * Denies the access of the user.
     * The default implementation will redirect the user to the login page if he is a guest;
     * if the user is already logged, a 403 HTTP exception will be thrown.
     * 
     * @param User|false $user the current user or boolean `false` in case of detached User component
     * @throws ForbiddenHttpException if the user is already logged in or in case of detached User component.
     */
    protected function denyAccess($user)
    {
        if ($user !== false && $user->getIsGuest()) {
            $user->loginRequired();
        } else {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }
    }
}
