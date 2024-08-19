<?php

namespace yii\permission\tests;

use Yii;
use Casbin\Persist\Adapters\Filter;
use Casbin\Exceptions\InvalidFilterTypeException;
use yii\db\ActiveQueryInterface;

class AdapterTest extends TestCase
{
    public function testEnforce()
    {
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data1', 'read'));

        $this->assertFalse(Yii::$app->permission->enforce('bob', 'data1', 'read'));
        $this->assertTrue(Yii::$app->permission->enforce('bob', 'data2', 'write'));

        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data2', 'read'));
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data2', 'write'));
    }

    public function testAddPolicy()
    {
        $this->assertFalse(Yii::$app->permission->enforce('eve', 'data3', 'read'));
        Yii::$app->permission->addPermissionForUser('eve', 'data3', 'read');
        $this->assertTrue(Yii::$app->permission->enforce('eve', 'data3', 'read'));
    }

    public function testAddPolicies()
    {
        $policies = [
            ['u1', 'd1', 'read'],
            ['u2', 'd2', 'read'],
            ['u3', 'd3', 'read'],
        ];
        Yii::$app->permission->clearPolicy();
        $this->assertEquals([], Yii::$app->permission->getPolicy());
        Yii::$app->permission->addPolicies($policies);
        $this->assertEquals($policies, Yii::$app->permission->getPolicy());
    }

    public function testSavePolicy()
    {
        $this->assertFalse(Yii::$app->permission->enforce('alice', 'data4', 'read'));

        $model = Yii::$app->permission->getModel();
        $model->clearPolicy();
        $model->addPolicy('p', 'p', ['alice', 'data4', 'read']);

        $adapter = Yii::$app->permission->getAdapter();
        $adapter->savePolicy($model);
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data4', 'read'));
    }

    public function testRemovePolicy()
    {
        $this->assertFalse(Yii::$app->permission->enforce('alice', 'data5', 'read'));

        Yii::$app->permission->addPermissionForUser('alice', 'data5', 'read');
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data5', 'read'));

        Yii::$app->permission->deletePermissionForUser('alice', 'data5', 'read');
        $this->assertFalse(Yii::$app->permission->enforce('alice', 'data5', 'read'));
    }

    public function testRemovePolicies()
    {
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], Yii::$app->permission->getPolicy());

        Yii::$app->permission->removePolicies([
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ]);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write']
        ], Yii::$app->permission->getPolicy());
    }

    public function testRemoveFilteredPolicy()
    {
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data1', 'read'));
        Yii::$app->permission->removeFilteredPolicy(1, 'data1');
        $this->assertFalse(Yii::$app->permission->enforce('alice', 'data1', 'read'));
        $this->assertTrue(Yii::$app->permission->enforce('bob', 'data2', 'write'));
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data2', 'read'));
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data2', 'write'));
        Yii::$app->permission->removeFilteredPolicy(1, 'data2', 'read');
        $this->assertTrue(Yii::$app->permission->enforce('bob', 'data2', 'write'));
        $this->assertFalse(Yii::$app->permission->enforce('alice', 'data2', 'read'));
        $this->assertTrue(Yii::$app->permission->enforce('alice', 'data2', 'write'));
        Yii::$app->permission->removeFilteredPolicy(2, 'write');
        $this->assertFalse(Yii::$app->permission->enforce('bob', 'data2', 'write'));
        $this->assertFalse(Yii::$app->permission->enforce('alice', 'data2', 'write'));
    }

    public function testUpdatePolicy()
    {
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], Yii::$app->permission->getPolicy());

        Yii::$app->permission->updatePolicy(
            ['alice', 'data1', 'read'],
            ['alice', 'data1', 'write']
        );

        Yii::$app->permission->updatePolicy(
            ['bob', 'data2', 'write'],
            ['bob', 'data2', 'read']
        );

        $this->assertEquals([
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], Yii::$app->permission->getPolicy());
    }

    public function testUpdatePolicies()
    {
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], Yii::$app->permission->getPolicy());

        $oldPolicies = [
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write']
        ];
        $newPolicies = [
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read']
        ];

        Yii::$app->permission->updatePolicies($oldPolicies, $newPolicies);

        $this->assertEquals([
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], Yii::$app->permission->getPolicy());
    }

    public function arrayEqualsWithoutOrder(array $expected, array $actual)
    {
        if (method_exists($this, 'assertEqualsCanonicalizing')) {
            $this->assertEqualsCanonicalizing($expected, $actual);
        } else {
            array_multisort($expected);
            array_multisort($actual);
            $this->assertEquals($expected, $actual);
        }
    }

    public function testUpdateFilteredPolicies()
    {
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], Yii::$app->permission->getPolicy());

        Yii::$app->permission->updateFilteredPolicies([["alice", "data1", "write"]], 0, "alice", "data1", "read");
        Yii::$app->permission->updateFilteredPolicies([["bob", "data2", "read"]], 0, "bob", "data2", "write");

        $policies = [
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
        ];
        $this->arrayEqualsWithoutOrder($policies, Yii::$app->permission->getPolicy());

        // test use updateFilteredPolicies to update all policies of a user
        $this->initTable();
        $this->refreshApplication();

        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];

        Yii::$app->permission->addPolicies($policies);
        $this->arrayEqualsWithoutOrder([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], Yii::$app->permission->getPolicy());

        Yii::$app->permission->updateFilteredPolicies([['alice', 'data1', 'write'], ['alice', 'data2', 'read']], 0, 'alice');
        Yii::$app->permission->updateFilteredPolicies([['bob', 'data1', 'write'], ["bob", "data2", "read"]], 0, 'bob');

        $policies = [
            ['alice', 'data1', 'write'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write']
        ];

        $this->arrayEqualsWithoutOrder($policies, Yii::$app->permission->getPolicy());

        // test if $fieldValues contains empty string
        $this->initTable();
        $this->refreshApplication();

        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];
        Yii::$app->permission->addPolicies($policies);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], Yii::$app->permission->getPolicy());

        Yii::$app->permission->updateFilteredPolicies([['alice', 'data1', 'write'], ['alice', 'data2', 'read']], 0, 'alice', '', '');
        Yii::$app->permission->updateFilteredPolicies([['bob', 'data1', 'write'], ["bob", "data2", "read"]], 0, 'bob', '', '');

        $policies = [
            ['alice', 'data1', 'write'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write']
        ];

        $this->arrayEqualsWithoutOrder($policies, Yii::$app->permission->getPolicy());

        // test if $fieldIndex is not zero
        $this->initTable();
        $this->refreshApplication();

        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];
        Yii::$app->permission->addPolicies($policies);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], Yii::$app->permission->getPolicy());

        Yii::$app->permission->updateFilteredPolicies([['alice', 'data1', 'edit'], ['bob', 'data1', 'edit']], 2, 'read');
        Yii::$app->permission->updateFilteredPolicies([['alice', 'data2', 'read'], ["bob", "data2", "read"]], 2, 'write');

        $policies = [
            ['alice', 'data1', 'edit'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'edit'],
            ['bob', 'data2', 'read'],
        ];

        $this->arrayEqualsWithoutOrder($policies, Yii::$app->permission->getPolicy());
    }

    public function testLoadFilteredPolicy()
    {
        Yii::$app->permission->clearPolicy();
        $adapter = Yii::$app->permission->getAdapter();
        $adapter->setFiltered(true);
        $this->assertEquals([], Yii::$app->permission->getPolicy());

        // invalid filter type
        try {
            $filter = ['alice', 'data1', 'read'];
            Yii::$app->permission->loadFilteredPolicy($filter);
            $exception = InvalidFilterTypeException::class;
            $this->fail("Expected exception $exception not thrown");
        } catch (InvalidFilterTypeException $exception) {
            $this->assertEquals("invalid filter type", $exception->getMessage());
        }

        // string
        $filter = "v0 = 'bob'";
        Yii::$app->permission->loadFilteredPolicy($filter);
        $this->assertEquals([
            ['bob', 'data2', 'write']
        ], Yii::$app->permission->getPolicy());

        // Filter
        $filter = new Filter(['v2'], ['read']);
        Yii::$app->permission->loadFilteredPolicy($filter);
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['data2_admin', 'data2', 'read'],
        ], Yii::$app->permission->getPolicy());

        // Closure
        Yii::$app->permission->loadFilteredPolicy(function (ActiveQueryInterface &$entity) {
            $entity->where(['v1' => 'data1']);
        });

        $this->assertEquals([
            ['alice', 'data1', 'read'],
        ], Yii::$app->permission->getPolicy());
    }
}
