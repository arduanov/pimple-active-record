<?php

namespace Record;

class QueryBuilder extends \Doctrine\DBAL\Query\QueryBuilder
{
    private $model_class;

    public function setModelClass($name){
        $this->model_class = $name;
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
        return $this->execute()->fetchAll(\PDO::FETCH_CLASS, $this->model_class,[true]);
    }


}