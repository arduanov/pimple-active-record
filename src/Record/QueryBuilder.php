<?php

namespace Record;

class QueryBuilder extends \Doctrine\DBAL\Query\QueryBuilder
{
    /**
     * @var Record
     */
    private $model;

    public function setModel(Record $model)
    {
        $this->model = $model;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  string $column
     * @param  string $operator
     * @param  mixed $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null, $boolean = 'and')
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $args = func_get_args();

        if (count($args) == 1) {
            parent::where($args[0]);
            return $this;
        }

        $column = $args[0];
        $boolean = 'and';
        $operator = '=';

        if (count($args) == 2) {
            $value = $args[1];
        }
        if (count($args) == 3) {
            $operator = $args[1];
            $value = $args[2];
        }
        if (count($args) == 4) {
            $operator = $args[1];
            $value = $args[2];
            $boolean = $args[3];
        }
        if (is_array($value)) {
            $operator = $operator == '=' ? 'in' : 'notIn';
            $where_clause = $this->expr()
                                 ->$operator($column, parent::createNamedParameter($value, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY));
        } else {
            $where_clause = $column . $operator . parent::createNamedParameter($value);
        }

        if ($boolean == 'and') {
            parent::andWhere($where_clause);
        } elseif ($boolean == 'or') {
            parent::orWhere($where_clause);
        }

        return $this;
    }


    /**
     * Find a model by its primary key.
     *
     * @param  mixed $id
     * @param  array $columns
     * @return $this->model
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }
        return $this->where('id', $id)->first();
    }

    /**
     * Find a model by its primary key.
     *
     * @param  array $ids
     * @param  array $columns
     * @return $this->model
     */
    public function findMany($ids, $columns = ['*'])
    {
        return $this->where('id', $ids)->get($columns);
    }


    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed $id
     * @param  array $columns
     * @return $this->model
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail($id, $columns = ['*'])
    {
        $result = $this->find($id, $columns);
        if (is_array($id)) {
            if (count($result) == count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }
        throw (new ModelNotFoundException)->setModel(get_class($this->model));
    }

    public function skip($rows_count)
    {
        parent::setFirstResult($rows_count);
        return $this;
    }

    public function take($rows_count)
    {
        parent::setMaxResults($rows_count);
        return $this;
    }


    /**
     * Execute the query and get the first result.
     *
     * @param  array $columns
     * @return $this->model
     */
    public function first($columns = ['*'])
    {
        $items = $this->take(1)->get($columns);
        return $items ? array_shift($items) : null;
    }

//    public function one()
//    {
//        $items = $this->get();
//
//        if (!$items) {
//            return null;
//        }
//        if (count($items) > 1) {
//            throw new \Exception('finded more than one');
//        }
//        return array_shift($items);
//    }

    public function get()
    {
        return parent::execute()->fetchAll(\PDO::FETCH_CLASS, get_class($this->model), [$this->model->app(), true]);
    }

    public function delete()
    {
        return parent::delete($this->model->tableName())->execute();
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->model, $scope = 'scope' . ucfirst($method))) {
            array_unshift($parameters, $this);
            return call_user_func_array([$this->model, $scope], $parameters);
        }
    }
}