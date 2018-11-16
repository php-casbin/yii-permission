<?php

namespace CasbinAdapter\Yii;

use yii\base\Component;
use Casbin\Model\Model;
use Casbin\Enforcer;
use CasbinAdapter\Yii\Models\CasbinRule;
use Yii;

/**
 * Casbin.
 *
 * @author techlee@qq.com
 */
class Casbin extends Component
{
    public $enforcer;

    public $adapter;

    public $model;

    public $config = [];

    public function __construct($config = [])
    {
        $this->config = $this->mergeConfig(
            require_once dirname(__DIR__).'/config/casbin.php',
            $this->config
        );

        $this->adapter = Yii::$container->get($this->config['adapter']);

        $this->model = new Model();
        if ('file' == $this->config['model']['config_type']) {
            $this->model->loadModel($this->config['model']['config_file_path']);
        } elseif ('text' == $this->configType) {
            $this->model->loadModel($this->config['model']['config_text']);
        }
    }

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     */
    public function init()
    {
        $db = CasbinRule::getDb();
        $tableName = CasbinRule::tableName();
        $table = $db->getTableSchema($tableName);
        if (!$table) {
            $res = $db->createCommand()->createTable($tableName, [
                'id' => 'pk',
                'ptype' => 'string',
                'v0' => 'string',
                'v1' => 'string',
                'v2' => 'string',
                'v3' => 'string',
                'v4' => 'string',
                'v5' => 'string',
            ])->execute();
        }
    }

    public function enforcer($newInstance = false)
    {
        if ($newInstance || is_null($this->enforcer)) {
            $this->init();
            $this->enforcer = new Enforcer($this->model, $this->adapter);
        }

        return $this->enforcer;
    }

    private function mergeConfig(array $a, array $b)
    {
        foreach ($a as $key => $val) {
            if (isset($b[$key])) {
                if (gettype($a[$key]) != gettype($b[$key])) {
                    continue;
                }
                if (is_array($a[$key])) {
                    $a[$key] = $this->mergeConfig($a[$key], $b[$key]);
                } else {
                    $a[$key] = $b[$key];
                }
            }
        }

        return $a;
    }

    /**
     * Calls the named method which is not a class method.
     *
     * This method will check if any attached behavior has
     * the named method and will execute it if available.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when an unknown method is being invoked.
     *
     * @param string $name   the method name
     * @param array  $params method parameters
     *
     * @return mixed the method return value
     *
     * @throws UnknownMethodException when calling unknown method
     */
    public function __call($name, $params)
    {
        foreach ($this->getBehaviors() as $object) {
            if ($object->hasMethod($name)) {
                return call_user_func_array([$object, $name], $params);
            }
        }

        return call_user_func_array([$this->enforcer(), $name], $params);
    }
}
