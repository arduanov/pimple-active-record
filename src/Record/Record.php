<?php

namespace Record;

use Pimple\Container;
use Doctrine\DBAL;
use Doctrine\Common\Inflector\Inflector;

/**
 * Class Record
 * @method $this foo() foo($parametersHere) explanation of the function
 */
class Record //implements \ArrayAccess
{
    protected static $app;
    protected static $builder;
    public static $exists = false;
    protected static $query;

    public function setApp(Container $app)
    {
        self::$app = $app;
    }

    /**
     * @return Container
     */
    public function app()
    {
        return self::$app;
    }

//    public function getQueryBuilder()
//    {
//        return $this->builder()->select('*')->from($this->tableName());
//    }
    /**
     * @return QueryBuilder
     */
    public function newQuery()
    {
        $query = new QueryBuilder($this->app()['db']);
        $query->setModel($this);
        $query->select('*')->from($this->tableName());
        return static::$query = $query;
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return QueryBuilder
     */
    public function getQuery()
    {
        return static::$query;
    }

    /**
     * Set the underlying query builder instance.
     *
     * @param  QueryBuilder $query
     * @return $this
     */
    public function setQuery($query)
    {
        static::$query = $query;
        return $this;
    }

    /**
     * Get all of the models from the database.
     *
     * @param  array|mixed $columns
     * @return array <$this>
     */
    public function all($columns = ['*'])
    {
        return $this->newQuery()->get($columns);
    }


    public function loadModels(array $modelsList)
    {
//        foreach ($modelsList as $name => $class) {
//            if (is_string($class)) {
//                $closure = function () use ($class) {
//                    return new $class();
//                };
//                $this->app()[$name] = $closure;
//            } elseif (is_callable($class)) {
//                $this->app()[$name] = $class;
//            }
//        }
        foreach ($modelsList as $name => $class) {
            if (is_callable($class)) {
                $callable = $class;
            } elseif (is_string($class)) {
                $callable = function () use ($class) {
                    return new $class();
                };
            }
            $this->app()[$name] = $this->app()->factory($callable);
        }
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
            static::$exists = true;
            $this->afterFetch();
        }
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array $attributes
     * @return $this
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    /**
     * Append attributes to query when building a query.
     *
     * @param  array|string $attributes
     * @return $this
     */
    public function append($attributes)
    {
        return $this->fill(array_merge((array)$this, $attributes));
    }

    protected function getValuesForDb()
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

    public function destroy($ids)
    {
        $count = 0;
        $ids = is_array($ids) ? $ids : func_get_args();

        foreach ($this->whereIn('id', $ids)->get() as $model) {
            if ($model->delete()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Generates a delete string and executes it.
     *
     * @throws \Exception
     * @return boolean True if delete was successful.
     */
    public function delete()
    {
        if (!static::$exists) {
            return false;
        }

        if (!$this->beforeDelete()) {
            return false;
        }
//        if (!isset($this->id)) {
//            throw new \Exception('cant delete without id');
//        }
        if (!empty($this->id)) {
            $criteria = ['id' => $this->id];
        } else {
            $criteria = (array)$this;
        }
        $this->app()['db']->delete($this->tableName(), $criteria);
        $this->afterDelete();

        static::$exists = false;

        return true;
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

//    /**
//     * @inheritdoc
//     */
//    public function offsetSet($offset, $value)
//    {
//        $this->$offset = $value;
//    }
//
//    /**
//     * @inheritdoc
//     */
//    public function offsetExists($offset)
//    {
//        return isset($this->$offset);
//    }
//
//    /**
//     * @inheritdoc
//     */
//    public function offsetUnset($offset)
//    {
//        unset($this->$offset);
//    }
//
//    /**
//     * @inheritdoc
//     */
//    public function offsetGet($offset)
//    {
//        return $this->$offset;
//    }
//
//    public function __call($name, $arguments)
//    {
//        return $this->builder()->$name();
//    }
    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
//        if (in_array($method, ['increment', 'decrement'])) {
//            return call_user_func_array([$this, $method], $parameters);
//        }
        $query = $this->newQuery();
        return call_user_func_array([$query, $method], $parameters);
    }
}