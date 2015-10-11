<?php

namespace Record;

use Pimple\Container;
use Doctrine\DBAL;
use Doctrine\Common\Inflector\Inflector;

class Record implements \ArrayAccess
{
    protected static $app;
    protected static $builder;

    public function setApp(Container $app)
    {
        self::$app = $app;
    }

    public function app()
    {
        return self::$app;
    }

//    public function getQueryBuilder()
//    {
//        return $this->builder()->select('*')->from($this->tableName());
//    }
    public function createBuilder()
    {
        $builder = new QueryBuilder($this->app()['db']);
        $builder->setModel($this);
        $builder->select('*')->from($this->tableName());

        return static::$builder = $builder;
    }

    /**
     * @return QueryBuilder
     */
    protected function builder()
    {
        return static::$builder;
    }

    public function loadModels(array $modelsList)
    {
        foreach ($modelsList as $name => $class) {
            if (is_string($class)) {
                $closure = function () use ($class) {
                    return new $class();
                };
                $this->app()[$name] = $closure;
            } elseif (is_callable($class)) {
                $this->app()[$name] = $class;
            }
        }
    }

    public function create(array $data = null)
    {
        $class = get_class($this);
        $model = new $class;

        if ($data) {
            $model->setFromData($data);
        }

        return $model;
    }

    /**
     * Returns a database table name.
     *
     * The name that is returned is based on the classname or on the TABLE_NAME
     * constant in that class if that constant exists.
     *
     * @param string $class_name
     * @return string Database table name.
     */
    final public function tableName($class_name = null)
    {
        if (!$class_name) {
            $class_name = get_class($this);
        }
        if (defined($class_name . '::TABLE_NAME')) {
            return constant($class_name . '::TABLE_NAME');
        }
        $reflection = new \ReflectionClass($class_name);
        $class_name = $reflection->getShortName();
        return Inflector::tableize($class_name);
    }

    /**
     * Constructor for the Record class.
     *
     * If the $data parameter is given and is an array, the constructor sets
     * the class's variables based on the key=>value pairs found in the array.
     *
     * @param boolean $is_pdo_fetch
     */
    public function __construct($is_pdo_fetch = false)
    {
        if ($is_pdo_fetch) {
            $this->afterFetch();
        }
    }

    /**
     * Sets the class's variables based on the key=>value pairs in the given array.
     *
     * @param array $data An array of key,value pairs.
     */
    protected function setFromData(array $data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    private function getValuesForDb()
    {
        $value_of = [];
        foreach ($this->getColumns() as $column) {
            $value_of[$column] = $this->$column;
        }
        // Make sure we don't try to add "id" field;
        if (isset($value_of['id'])) {
            unset($value_of['id']);
        }
        return $value_of;
    }

    /**
     * Generates an insert or update string from the supplied data and executes it
     *
     * @return boolean True when the insert or update succeeded.
     */
    public function save()
    {
        if (!$this->beforeSave()) {
            return false;
        }
        if (empty($this->id)) {
            if (!$this->beforeInsert()) {
                return false;
            }
            $value_of = $this->getValuesForDb();
            $return = $this->app()['db']->insert($this->tableName(), $value_of);
            if (in_array('id', $this->getColumns())) {
                $this->id = $this->app()['db']->lastInsertId();
            }

            if (!$this->afterInsert()) {
                return false;
            }
        } else {
            if (!$this->beforeUpdate()) {
                return false;
            }

            $value_of = $this->getValuesForDb();
            $return = $this->app()['db']->update($this->tableName(), $value_of, ['id' => $this->id]);

            if (!$this->afterUpdate()) {
                return false;
            }
        }
        if (!$this->afterSave()) {
            return false;
        }
        return $return;
    }

    /**
     * Generates a delete string and executes it.
     *
     * @throws \Exception
     * @return boolean True if delete was successful.
     */
    public function delete()
    {
        $criteria = [];
        if (!empty($this->id)) {
            $criteria = ['id = ' => $this->id];
        } else {
            foreach ($this->getColumns() as $column) {
                if (isset($this->$column)) {
                    $criteria[$column . '='] = $this->$column;
                }
            }
        }
        return $this->deleteWhere($criteria);
    }

    public function deleteWhere(array $criteria)
    {
        if (empty($criteria)) {
            throw new \Exception('empty criteria');
        }
        if (!$this->beforeDelete()) {
            return false;
        }

        $return = $this->where($criteria)->delete();

        if (!$this->afterDelete()) {
            $this->save();
            return false;
        }
        return $return;
    }

    /**
     * Returns an array of all columns in the table.
     *
     * It is a good idea to rewrite this method in all your model classes.
     * This function is used in save() for creating the insert and/or update
     * sql query.
     *
     * @return array
     */
    public function getColumns()
    {
        return array_keys(get_object_vars($this));
    }

    public function where(array $where)
    {
        $this->createBuilder();
        foreach ($where as $key => $val) {
            $this->builder()->where($key . $this->builder()->createNamedParameter($val));
        }
//        var_dump(static::$builder->getSql());exit;
//        echo $this->builder()->getSQL();
//        exit;

        return $this->builder();
    }

    /**
     * @param $id
     * @return $this
     * @throws \Exception
     */
    public function find($id)
    {
        return $this->where(['id = ' => $id])->one();
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->createBuilder()->all();
    }

    /**
     * Allows sub-classes do stuff before a Record is saved.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeSave()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is inserted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeInsert()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is updated.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeUpdate()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is deleted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeDelete()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is fetched.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterFetch()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is saved.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterSave()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is inserted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterInsert()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is updated.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterUpdate()
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is deleted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterDelete()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * @inheritdoc
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }
}