<?php

namespace yii\permission\tests\support;

use yii\base\Component;
use yii\web\IdentityInterface;

class UserIdentity extends Component implements IdentityInterface
{
    private static $ids = [
        'alice',
        'bob'
    ];

    private static $tokens = [
        'token1' => 'alice',
        'token2' => 'bob'
    ];

    private $_id;

    private $_token;

    public static function findIdentity($id)
    {
        if (in_array($id, static::$ids)) {
            $identitiy = new static();
            $identitiy->_id = $id;
            return $identitiy;
        }
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        if (isset(static::$tokens[$token])) {
            $id = static::$tokens[$token];
            $identitiy = new static();
            $identitiy->_id = $id;
            $identitiy->_token = $token;
            return $identitiy;
        }
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getAuthKey()
    {
        return null;
    }

    public function validateAuthKey($authKey)
    {
        return true;
    }
}
