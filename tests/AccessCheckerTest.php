<?php

namespace Yii\Permission\Tests;

class AccessCheckerTest extends TestCase
{
    public function testYii3UserHasPermission(): void
    {
        $accessChecker = $this->getAccessChecker();
        $this->assertTrue($accessChecker->userHasPermission('alice', 'data1', ['read']));
        $this->assertTrue($accessChecker->userHasPermission('alice', 'data1,read'));
        $this->assertFalse($accessChecker->userHasPermission('bob', 'data1', ['read']));
        $this->assertTrue($accessChecker->userHasPermission('bob', 'data2', ['write']));
    }
}
