<?php

namespace Record;

class QueryBuilder extends \Doctrine\DBAL\Query\QueryBuilder
{
    private $model;

    public function setModel($model)
    {
        $this->model = $model;
    }

    public function one()
    {
//        echo $this->getSQL();exit;
        $items = $this->all();

        if (!$items) {
            return null;
        }
        if (count($items) > 1) {
            throw new \Exception('finded more than one');
        }
        return array_shift($items);
    }

    public function all()
    {
        return $this->execute()->fetchAll(\PDO::FETCH_CLASS, get_class($this->model), [true]);
    }

    public function delete()
    {
        return parent::delete($this->model->tableName())->execute();
    }

}