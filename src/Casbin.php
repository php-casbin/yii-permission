<?php

namespace yii\casbin;

use Casbin\Enforcer;
use Casbin\Model\Model;
use yii\base\Component;
use yii\db\Connection;
use yii\db\SchemaBuilderTrait;
use yii\di\exceptions\InvalidConfigException;
use yii\helpers\Yii;

/**
 * Class Casbin
 * @package docs\components\casbin
 *
 */
class Casbin extends Component
{
    use SchemaBuilderTrait;

    private $enforcer;
    public $modelLoadType;
    public $modelFilePath;
    public $modelText;
    public $autoInitTable = true;
    public $db = 'db';

    public function getDb()
    {
        /** @var Connection $db */
        $db = Yii::get($this->db);

        return $db;
    }

    private function initTable()
    {
        $db = $this->getDb();
        $table = $db->getTableSchema(Adapter::TABLE);
        if (!$table) {
            $db->createCommand()->createTable(Adapter::TABLE, [
                'id' => $this->primaryKey()->comment('主键 id'),
                'ptype' => $this->string()->notNull()->comment('policy type'),
                'v0' => $this->string()->notNull()->defaultValue('')->comment('规则 v0'),
                'v1' => $this->string()->notNull()->defaultValue('')->comment('规则 v1'),
                'v2' => $this->string()->notNull()->defaultValue('')->comment('规则 v2'),
                'v3' => $this->string()->notNull()->defaultValue('')->comment('规则 v3'),
                'v4' => $this->string()->notNull()->defaultValue('')->comment('规则 v4'),
                'v5' => $this->string()->notNull()->defaultValue('')->comment('规则 v5'),
            ])->execute();
        }
    }

    /**
     * @return Enforcer
     * @throws InvalidConfigException
     * @throws \Casbin\Exceptions\CasbinException
     */
    public function getEnforcer()
    {
        if (is_null($this->enforcer)) {
            if ($this->autoInitTable) {
                $this->initTable();
            }
            $modelInstance = new Model();
            if ('file' == $this->modelLoadType) {
                $modelInstance->loadModel($this->modelFilePath);
            } elseif ('text' == $this->modelLoadType) {
                $modelInstance->loadModelFromText($this->modelText);
            } else {
                throw new InvalidConfigException();
            }
            $adapter = new Adapter($this->getDb());
            $this->enforcer = new Enforcer($modelInstance, $adapter);
        }
        return $this->enforcer;
    }
}