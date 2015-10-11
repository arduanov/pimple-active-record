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

//    public function testInsert()
//    {
//        $post = $this->app['post.model']->create(['title' => 'title', 'slug' => 'slug']);
//        $post->save();
//
//        $this->assertEquals(1, $post->id);
//        $db_post = $this->app['post.model']->find(1);
//        $this->assertEquals($post->title,$db_post->title);
//    }


    public function testCRUD()
    {
        /**
         * testSave
         */
        $post = $this->app['post.model']->create(['title' => 'title', 'slug' => 'slug']);
        $post->save();
        $post2 = $this->app['post.model']->create(['title' => 'title1', 'slug' => 'slug2']);
        $post2->save();

        $this->assertEquals(2, $post2->id);

        /**
         * find by id
         */
        $db_post = $this->app['post.model']->find(2);
        $this->assertEquals($post2->title, $db_post->title);


        /**
         * find all
         */
        $db_post_all = $this->app['post.model']->all();
        $this->assertCount(2, $db_post_all);

        /**
         * delete
         */
        $delete_result = $post2->delete();
        $this->assertTrue($delete_result);


    }


}
