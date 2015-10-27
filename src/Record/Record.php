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
    protected $_app;
    protected $_exists = false;
    protected $_query;

    public function setApp(Container $app)
    {
        $this->_app = $app;
    }

    /**
     * @return Container
     */
    public function app()
    {
        return $this->_app;
    }

    /**
     * @return QueryBuilder
     */
    public function newQuery()
    {
        $query = new QueryBuilder($this->app()['db']);
        $query->setModel($this);
        $query->select('*')->from($this->tableName());
        return $this->_query = $query;
    }

//    /**
//     * Get the underlying query builder instance.
//     *
//     * @return QueryBuilder
//     */
//    public function getQuery()
//    {
//        return $this->_query;
//    }
//
//    /**
//     * Set the underlying query builder instance.
//     *
//     * @param  QueryBuilder $query
//     * @return $this
//     */
//    public function setQuery($query)
//    {
//        $this->_query = $query;
//        return $this;
//    }

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
     * @param Container $app
     * @param boolean $is_pdo_fetch
     */
    public function __construct(Container $app, $is_pdo_fetch = false)
    {
        $this->setApp($app);

        if ($is_pdo_fetch) {
            $this->_exists = true;
            $this->afterFetch($this->app());
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

    private function getValuesForDb()
    {
        $value_of = [];
        foreach ($this->getColumns() as $column) {
            $value_of[$column] = $this->$column;
        }
        // Make sure we don't try to add "id" field;
        if (array_key_exists('id', $value_of)) {
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
        if (!$this->beforeSave($this->app())) {
            return false;
        }
        if (empty($this->id)) {
            if (!$this->beforeInsert($this->app())) {
                return false;
            }
            $value_of = $this->getValuesForDb();
            $return = $this->app()['db']->insert($this->tableName(), $value_of);
            if (in_array('id', $this->getColumns())) {
                $this->id = $this->app()['db']->lastInsertId();
            }

            if (!$this->afterInsert($this->app())) {
                return false;
            }
        } else {
            if (!$this->beforeUpdate($this->app())) {
                return false;
            }

            $value_of = $this->getValuesForDb();
            $return = $this->app()['db']->update($this->tableName(), $value_of, ['id' => $this->id]);

            if (!$this->afterUpdate($this->app())) {
                return false;
            }
        }
        if (!$this->afterSave($this->app())) {
            return false;
        }
        return $return;
    }

    public function destroy($ids)
    {
        $count = 0;
        $ids = is_array($ids) ? $ids : func_get_args();
        foreach ($this->where('id', $ids)->get() as $model) {
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
        if (!$this->_exists) {
            return false;
        }

        if (!$this->beforeDelete($this->app())) {
            return false;
        }

        if (!empty($this->id)) {
            $criteria = ['id' => $this->id];
        } else {
            $criteria = (array)$this;
        }
        $this->app()['db']->delete($this->tableName(), $criteria);
        $this->afterDelete($this->app());

        $this->_exists = false;

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
        return array_filter(array_keys(get_object_vars($this)), function ($val) {
            return substr($val, 0, 1) != '_';
        });
    }

    /**
     * Allows sub-classes do stuff before a Record is saved.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeSave(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is inserted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeInsert(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is updated.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeUpdate(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff before a Record is deleted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function beforeDelete(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is fetched.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterFetch(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is saved.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterSave(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is inserted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterInsert(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is updated.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterUpdate(Container $app)
    {
        return true;
    }

    /**
     * Allows sub-classes do stuff after a Record is deleted.
     *
     * @return boolean True if the actions succeeded.
     */
    protected function afterDelete(Container $app)
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
        $query = $this->newQuery();
        return call_user_func_array([$query, $method], $parameters);
    }
}