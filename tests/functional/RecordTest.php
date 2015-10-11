<?php
require 'Model/Post.php';
require 'Model/Comment.php';

use Pimple\Container;

class RecordTest extends \PHPUnit_Framework_TestCase
{
    protected $app;
    protected $record;

    protected function setUp()
    {
        $this->app = $app = new Container();
        $db_config = [
            'driver' => 'pdo_sqlite',
            'dbname' => 'sqlite:///:memory:',
        ];

        $models = [
            'post.model' => function () {
                return new Post();
            },
            'comment.model' => 'Comment',
        ];

        $app['db'] = function () use ($db_config) {
            $db = \Doctrine\DBAL\DriverManager::getConnection($db_config);
            $schema = file_get_contents(codecept_data_dir() . '/dump.sql');
            $db->exec($schema);
            return $db;
        };

        $this->record = $record = new \Record\Record();
        $record->setApp($app);
        $record->loadModels($models);
    }

    protected function tearDown()
    {
    }

    public function testContainer()
    {
        $this->assertInstanceOf('Pimple\Container', $this->record->app());
    }

    public function testLoadModels()
    {
        $this->assertInstanceOf('Post', $this->app['post.model']);
        $this->assertInstanceOf('Comment', $this->app['comment.model']);
    }


}
