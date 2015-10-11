<?php
class Comment extends Record\Record
{
    public $id;
    public $username;
    public $post_id;
    const TABLE_NAME = 'table_comment';
}