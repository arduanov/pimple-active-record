<?php
use \Record\QueryBuilder;

class Post extends Record\Record
{
    public $id;
    public $slug;
    public $title;

    public function scopeTitle(QueryBuilder $query, $name)
    {
//        var_dump($type);exit;
        return $query->where('title', $name);
    }
}