<?php

declare(strict_types=1);

namespace Yii\Permission\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

/**
 * Creates casbin_rule table.
 */
final class M240729000000CreateCasbinRuleTable implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%casbin_rule}}', [
            'id' => 'pk',
            'ptype' => 'string(255)',
            'v0' => 'string(255)',
            'v1' => 'string(255)',
            'v2' => 'string(255)',
            'v3' => 'string(255)',
            'v4' => 'string(255)',
            'v5' => 'string(255)',
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%casbin_rule}}');
    }
}
