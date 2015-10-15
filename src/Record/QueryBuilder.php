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
     * Add a "where in" clause to the query.
     *
     * @param  string $column
     * @param  mixed $values
     * @param  string $boolean
     * @param  bool $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $operator = $not ? '<>' : '=';
        $this->where($column, $operator, $values, $boolean, $not);
        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param  string $column
     * @param  mixed $values
     * @return $this
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
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
        $this->where($column, $operator, $value, 'or');
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        $args = func_get_args();

        if (!$args && $args > 4) {
            throw new \Exception('bad params count');
        }

        if (count($args) == 1) {
            parent::where($args[0]);
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
            $this->expr()->$operator($column, parent::createNamedParameter($value, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY));
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

    public function findMany($rows_count)
    {
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
        return $items ? array_shift($items) : [];
    }

    public function one()
    {
        $items = $this->get();

        if (!$items) {
            return null;
        }
        if (count($items) > 1) {
            throw new \Exception('finded more than one');
        }
        return array_shift($items);
    }

    public function get()
    {
        return parent::execute()->fetchAll(\PDO::FETCH_CLASS, get_class($this->model), [true]);
    }

    public function delete()
    {
        $where_clause = parent::getQueryPart('where');
        if ($where_clause) {
            return parent::delete($this->model->tableName())->execute();
        }
        return false;
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
//        if (isset($this->macros[$method])) {
//            array_unshift($parameters, $this);
//            return call_user_func_array($this->macros[$method], $parameters);
//        } else
        if (method_exists($this->model, $scope = 'scope' . ucfirst($method))) {
            return $this->model->$scope($this, $parameters);
//            call_user_func_array([$this->query, $method], $parameters);
        }
//        $result = call_user_func_array([$this->query, $method], $parameters);
//        return in_array($method, $this->passthru) ? $result : $this;
    }
}