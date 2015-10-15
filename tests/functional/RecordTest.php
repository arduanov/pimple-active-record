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
            $sql = file_get_contents(codecept_data_dir() . '/dump.sql');
            $db->exec($sql);
            return $db;
        };

        $this->record = $record = new \Record\Record();
        $record->setApp($app);
        $record->loadModels($models);

        $this->loadFixtures();
    }

    protected function tearDown()
    {
    }

    protected function loadFixtures()
    {
        $post = $this->app['post.model']->fill(['title' => 'title', 'slug' => 'slug']);
        $post->save();
        $post2 = $this->app['post.model']->fill(['title' => 'title2', 'slug' => 'slug2']);
        $post2->save();
        $post3 = $this->app['post.model']->fill(['title' => 'title44', 'slug' => 'slug2']);
        $post3->save();
    }

    public function testAutocomplete()
    {
//        $record = new \Record\Record();
//        $record->foo('rrr')->
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

    public function testTableName()
    {
        $table = $this->app['post.model']->tableName();
        $this->assertEquals('post', $table);
        $table = $this->app['comment.model']->tableName();
        $this->assertEquals('table_comment', $table);
    }


    public function testFind()
    {
        $post = $this->app['post.model']->find(2);

        $this->assertEquals(2, $post->id);
        $this->assertEquals('title2', $post->title);
        $this->assertEquals('slug2', $post->slug);
    }


    public function testAll()
    {
        $all = $this->app['post.model']->all();
        $this->assertCount(3, $all);
    }

    public function testUpdate()
    {
        $post = $this->app['post.model']->find(2);
        $post->slug = 99;
        $result_id = $post->save();
        $this->assertEquals(1, $result_id);
    }

    public function testWhere()
    {
        $posts  = $this->app['post.model']->where('id', '>=', 2)->get();
        $this->assertCount(2, $posts);
    }

    public function testDelete()
    {
        $delete_result = $this->app['post.model']->find(1)->delete();
        $this->assertEquals(1, $delete_result);
    }


}
