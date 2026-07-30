<?php

namespace Yii\Permission\Models;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * CasbinRule active record model.
 *
 * @property int $id
 * @property string $ptype
 * @property string|null $v0
 * @property string|null $v1
 * @property string|null $v2
 * @property string|null $v3
 * @property string|null $v4
 * @property string|null $v5
 *
 * @author leeqvip@gmail.com
 */
#[\AllowDynamicProperties]
class CasbinRule extends ActiveRecord
{
    /**
     * @var ConnectionInterface|null Database connection instance specifically for this model
     */
    private ?ConnectionInterface $db = null;

    /**
     * @var string|null Custom table name
     */
    private ?string $customTableName = null;

    /**
     * Constructor.
     *
     * @param ConnectionInterface|null $db
     * @param string|null $tableName
     */
    public function __construct(
        ?ConnectionInterface $db = null,
        ?string $tableName = null
    ) {
        $this->db = $db;
        $this->customTableName = $tableName;
    }

    /**
     * Returns the table name.
     *
     * @return string table name
     */
    public function tableName(): string
    {
        return $this->customTableName ?? parent::tableName();
    }

    /**
     * Gets the database connection instance for this ActiveRecord.
     *
     * @return ConnectionInterface
     */
    public function db(): ConnectionInterface
    {
        if ($this->db !== null) {
            return $this->db;
        }

        return parent::db();
    }


}
