<?php

namespace yii\permission\tests;

use Yii;
use yii\permission\components\PermissionChecker;
use yii\permission\components\PermissionControl;
use yii\permission\tests\support\TestController;
use yii\permission\tests\support\UserIdentity;
use yii\web\ForbiddenHttpException;
use yii\web\User;

class LocalizationTest extends TestCase
{
    private $baseBehaviors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initBaseBehaviors();
    }

    public static function permissionCheckerProvider()
    {
        return [
            ['alice', 'data1,read', true],
            ['bob', 'data1,read', false],
            ['bob', 'data2,write', true],
            ['alice', 'data2,read', true],
            ['alice', 'data2,write', true]
        ];
    }

    /**
     * @dataProvider permissionCheckerProvider
     */
    public function testPermissionChecker($sub, $params, $expected)
    {
        $user = $this->mockUser($sub);
        $auth = $this->mockAuthManager();

        $user->accessChecker = $auth;
        $this->assertEquals($expected, $user->can($params));
    }

    public function testPermissionControlBasic()
    {
        $controller = new TestController('test', $this->app, [
            'behaviors' => [
                'permission' => $this->baseBehaviors
            ],
        ]);
        $this->assertEquals('create success', $controller->runAction('create-post'));
        $this->assertEquals('update success', $controller->runAction('update-post'));

        // try contained actions
        try {
            $controller->runAction('delete-post');
            $this->fail('Expected exception not thrown');
        } catch (ForbiddenHttpException $e) {
            $this->assertInstanceOf(ForbiddenHttpException::class, $e);
        }

        // try not contained actions
        try {
            $controller->runAction('comment');
            $this->fail('Expected exception not thrown');
        } catch (ForbiddenHttpException $e) {
            $this->assertInstanceOf(ForbiddenHttpException::class, $e);
        }

        // try 
    }

    public function testPermissionControlWithGlobalDenyCallback()
    {
        $this->baseBehaviors['denyCallback'] = function ($rule, $action) {
            throw new ForbiddenHttpException('forbidden by global');
        };
        $controller = new TestController('test', $this->app, [
            'behaviors' => [
                'permission' => $this->baseBehaviors
            ]
        ]);

        // try contained actions
        try {
            $controller->runAction('delete-post');
            $this->fail('Expected exception not thrown');
        } catch (ForbiddenHttpException $e) {
            $this->assertEquals('forbidden by global', $e->getMessage());
        }
    }

    public function testPermissionControlWithDenyCallback()
    {
        // try local denyCallback when `allow` is false
        $this->baseBehaviors['policy'][0]['allow'] = false;
        $this->baseBehaviors['policy'][0]['denyCallback'] = function ($rule, $action) {
            throw new ForbiddenHttpException('forbidden by local');
        };
        $controller = new TestController('test', $this->app, [
            'behaviors' => [
                'permission' => $this->baseBehaviors
            ]
        ]);

        try {
            $controller->runAction('create-post');
            $this->fail('Expected exception not thrown');
        } catch (ForbiddenHttpException $e) {
            $this->assertEquals('forbidden by local', $e->getMessage());
        }

        // try global denyCallback when `allow` is false
        $this->baseBehaviors['policy'][0]['denyCallback'] = null;
        $controller = new TestController('test', $this->app, [
            'behaviors' => [
                'permission' => $this->baseBehaviors
            ]
        ]);

        try {
            $controller->runAction('create-post');
            $this->fail('Expected exception not thrown');
        } catch (ForbiddenHttpException $e) {
            $this->assertEquals(Yii::t('yii', 'You are not allowed to perform this action.'), $e->getMessage());
        }

        // try custom global denyCallback when `allow` is false
        $this->baseBehaviors['denyCallback'] = function ($rule, $action) {
            throw new ForbiddenHttpException('forbidden by global');
        };
        $controller = new TestController('test', $this->app, [
            'behaviors' => [
                'permission' => $this->baseBehaviors
            ]
        ]);

        try {
            $controller->runAction('create-post');
            $this->fail('Expected exception not thrown');
        } catch (ForbiddenHttpException $e) {
            $this->assertEquals('forbidden by global', $e->getMessage());
        }

        // try denyCallback with not thrown exception when `allow` is false
        $this->baseBehaviors['denyCallback'] = function ($rule, $action) {};
        $controller = new TestController('test', $this->app, [
            'behaviors' => [
                'permission' => $this->baseBehaviors
            ]
        ]);
        $this->assertNull($controller->runAction('delete-post'));

        // try denyCallback with not thrown exception when `allow` is true
        $this->baseBehaviors['policy'][0]['allow'] = true;
        $controller = new TestController('test', $this->app, [
            'behaviors' => [
                'permission' => $this->baseBehaviors
            ]
        ]);
        $this->assertNull($controller->runAction('delete-post'));
    }

    protected function initBaseBehaviors()
    {
        $this->baseBehaviors = [
            'class' => PermissionControl::class,
            'user' => $this->mockUser('alice'),
            'only' => ['create-post', 'update-post', 'delete-post', 'comment'],
            'policy' => [
                [
                    'allow' => true,
                    'actions' => ['create-post', 'update-post'],
                    'enforce' => ['data1', 'read']
                ],
                [
                    'allow' => true,
                    'actions' => ['delete-post'],
                    'enforce' => ['data1', 'write']
                ]
            ]
        ];
    }

    protected function mockUser($userId)
    {
        $user = new User([
            'identityClass' => UserIdentity::class,
            'enableAutoLogin' => false,
        ]);
        $user->setIdentity(UserIdentity::findIdentity($userId));
        return $user;
    }

    protected function mockAuthManager()
    {
        $auth = new PermissionChecker();
        return $auth;
    }
}
