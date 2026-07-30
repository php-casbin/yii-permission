<?php

namespace Yii\Permission\Tests;

use Casbin\Persist\Adapters\Filter;
use Casbin\Exceptions\InvalidFilterTypeException;
use Yiisoft\ActiveRecord\ActiveQueryInterface;

class AdapterTest extends TestCase
{
    public function testInit()
    {
        $enforcer = $this->getEnforcer();
        $this->assertInstanceOf(\Casbin\Enforcer::class, $enforcer);
        $this->assertTrue($enforcer->enforce('alice', 'data1', 'read'));
    }

    public function testDatabaseCustomConfiguration()
    {
        $params = [
            'casbin/yii-permission' => [
                'database' => [
                    'connection' => self::$dbConnection,
                    'casbin_rules_table' => '{{%custom_casbin_rule}}',
                ]
            ]
        ];

        $diDefinitions = require dirname(__DIR__) . '/config/di.php';
        $casbinRule = $diDefinitions[\Yii\Permission\Models\CasbinRule::class]($this->container);

        $this->assertEquals('{{%custom_casbin_rule}}', $casbinRule->tableName());
        $this->assertSame(self::$dbConnection, $casbinRule->db());
    }

    public function testEnforce()
    {
        $enforcer = $this->getEnforcer();
        $this->assertTrue($enforcer->enforce('alice', 'data1', 'read'));

        $this->assertFalse($enforcer->enforce('bob', 'data1', 'read'));
        $this->assertTrue($enforcer->enforce('bob', 'data2', 'write'));

        $this->assertTrue($enforcer->enforce('alice', 'data2', 'read'));
        $this->assertTrue($enforcer->enforce('alice', 'data2', 'write'));
    }

    public function testAddPolicy()
    {
        $enforcer = $this->getEnforcer();
        $this->assertFalse($enforcer->enforce('eve', 'data3', 'read'));
        $enforcer->addPermissionForUser('eve', 'data3', 'read');
        $this->assertTrue($enforcer->enforce('eve', 'data3', 'read'));
    }

    public function testAddPolicies()
    {
        $enforcer = $this->getEnforcer();
        $policies = [
            ['u1', 'd1', 'read'],
            ['u2', 'd2', 'read'],
            ['u3', 'd3', 'read'],
        ];
        $enforcer->clearPolicy();
        $this->assertEquals([], $enforcer->getPolicy());
        $enforcer->addPolicies($policies);
        $this->assertEquals($policies, $enforcer->getPolicy());
    }

    public function testSavePolicy()
    {
        $enforcer = $this->getEnforcer();
        $this->assertFalse($enforcer->enforce('alice', 'data4', 'read'));

        $model = $enforcer->getModel();
        $model->clearPolicy();
        $model->addPolicy('p', 'p', ['alice', 'data4', 'read']);

        $adapter = $enforcer->getAdapter();
        $adapter->savePolicy($model);
        $this->assertTrue($enforcer->enforce('alice', 'data4', 'read'));
    }

    public function testRemovePolicy()
    {
        $enforcer = $this->getEnforcer();
        $this->assertFalse($enforcer->enforce('alice', 'data5', 'read'));

        $enforcer->addPermissionForUser('alice', 'data5', 'read');
        $this->assertTrue($enforcer->enforce('alice', 'data5', 'read'));

        $enforcer->deletePermissionForUser('alice', 'data5', 'read');
        $this->assertFalse($enforcer->enforce('alice', 'data5', 'read'));
    }

    public function testRemovePolicies()
    {
        $enforcer = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $enforcer->getPolicy());

        $enforcer->removePolicies([
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ]);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write']
        ], $enforcer->getPolicy());
    }

    public function testRemoveFilteredPolicy()
    {
        $enforcer = $this->getEnforcer();
        $this->assertTrue($enforcer->enforce('alice', 'data1', 'read'));
        $enforcer->removeFilteredPolicy(1, 'data1');
        $this->assertFalse($enforcer->enforce('alice', 'data1', 'read'));
        $this->assertTrue($enforcer->enforce('bob', 'data2', 'write'));
        $this->assertTrue($enforcer->enforce('alice', 'data2', 'read'));
        $this->assertTrue($enforcer->enforce('alice', 'data2', 'write'));
        $enforcer->removeFilteredPolicy(1, 'data2', 'read');
        $this->assertTrue($enforcer->enforce('bob', 'data2', 'write'));
        $this->assertFalse($enforcer->enforce('alice', 'data2', 'read'));
        $this->assertTrue($enforcer->enforce('alice', 'data2', 'write'));
        $enforcer->removeFilteredPolicy(2, 'write');
        $this->assertFalse($enforcer->enforce('bob', 'data2', 'write'));
        $this->assertFalse($enforcer->enforce('alice', 'data2', 'write'));
    }

    public function testUpdatePolicy()
    {
        $enforcer = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $enforcer->getPolicy());

        $enforcer->updatePolicy(
            ['alice', 'data1', 'read'],
            ['alice', 'data1', 'write']
        );

        $enforcer->updatePolicy(
            ['bob', 'data2', 'write'],
            ['bob', 'data2', 'read']
        );

        $this->assertEquals([
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $enforcer->getPolicy());
    }

    public function testUpdatePolicies()
    {
        $enforcer = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $enforcer->getPolicy());

        $oldPolicies = [
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write']
        ];
        $newPolicies = [
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read']
        ];

        $enforcer->updatePolicies($oldPolicies, $newPolicies);

        $this->assertEquals([
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $enforcer->getPolicy());
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
        $enforcer = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $enforcer->getPolicy());

        $enforcer->updateFilteredPolicies([["alice", "data1", "write"]], 0, "alice", "data1", "read");
        $enforcer->updateFilteredPolicies([["bob", "data2", "read"]], 0, "bob", "data2", "write");

        $policies = [
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
        ];
        $this->arrayEqualsWithoutOrder($policies, $enforcer->getPolicy());

        // test use updateFilteredPolicies to update all policies of a user
        $this->initTable();
        $this->refreshApplication();
        $enforcer = $this->getEnforcer();

        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];

        $enforcer->addPolicies($policies);
        $this->arrayEqualsWithoutOrder([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], $enforcer->getPolicy());

        $enforcer->updateFilteredPolicies([['alice', 'data1', 'write'], ['alice', 'data2', 'read']], 0, 'alice');
        $enforcer->updateFilteredPolicies([['bob', 'data1', 'write'], ["bob", "data2", "read"]], 0, 'bob');

        $policies = [
            ['alice', 'data1', 'write'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write']
        ];

        $this->arrayEqualsWithoutOrder($policies, $enforcer->getPolicy());

        // test if $fieldValues contains empty string
        $this->initTable();
        $this->refreshApplication();
        $enforcer = $this->getEnforcer();

        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];
        $enforcer->addPolicies($policies);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], $enforcer->getPolicy());

        $enforcer->updateFilteredPolicies([['alice', 'data1', 'write'], ['alice', 'data2', 'read']], 0, 'alice', '', '');
        $enforcer->updateFilteredPolicies([['bob', 'data1', 'write'], ["bob", "data2", "read"]], 0, 'bob', '', '');

        $policies = [
            ['alice', 'data1', 'write'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write']
        ];

        $this->arrayEqualsWithoutOrder($policies, $enforcer->getPolicy());

        // test if $fieldIndex is not zero
        $this->initTable();
        $this->refreshApplication();
        $enforcer = $this->getEnforcer();

        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];
        $enforcer->addPolicies($policies);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], $enforcer->getPolicy());

        $enforcer->updateFilteredPolicies([['alice', 'data1', 'edit'], ['bob', 'data1', 'edit']], 2, 'read');
        $enforcer->updateFilteredPolicies([['alice', 'data2', 'read'], ["bob", "data2", "read"]], 2, 'write');

        $policies = [
            ['alice', 'data1', 'edit'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'edit'],
            ['bob', 'data2', 'read'],
        ];

        $this->arrayEqualsWithoutOrder($policies, $enforcer->getPolicy());
    }

    public function testLoadFilteredPolicy()
    {
        $enforcer = $this->getEnforcer();
        $enforcer->clearPolicy();
        $adapter = $enforcer->getAdapter();
        $adapter->setFiltered(true);
        $this->assertEquals([], $enforcer->getPolicy());

        // invalid filter type
        try {
            $filter = ['alice', 'data1', 'read'];
            $enforcer->loadFilteredPolicy($filter);
            $exception = InvalidFilterTypeException::class;
            $this->fail("Expected exception $exception not thrown");
        } catch (InvalidFilterTypeException $exception) {
            $this->assertEquals("invalid filter type", $exception->getMessage());
        }

        // string
        $filter = "v0 = 'bob'";
        $enforcer->loadFilteredPolicy($filter);
        $this->assertEquals([
            ['bob', 'data2', 'write']
        ], $enforcer->getPolicy());

        // Filter
        $filter = new Filter(['v2'], ['read']);
        $enforcer->loadFilteredPolicy($filter);
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['data2_admin', 'data2', 'read'],
        ], $enforcer->getPolicy());

        // Closure
        $enforcer->loadFilteredPolicy(function (ActiveQueryInterface &$entity) {
            $entity->where(['v1' => 'data1']);
        });

        $this->assertEquals([
            ['alice', 'data1', 'read'],
        ], $enforcer->getPolicy());
    }
}
