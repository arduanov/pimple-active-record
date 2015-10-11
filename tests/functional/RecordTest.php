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

    public function testtableName()
    {
        $table = $this->app['post.model']->tableName();
        $this->assertEquals('post', $table);
        $table = $this->app['comment.model']->tableName();
        $this->assertEquals('table_comment', $table);
    }


    public function testCRUD()
    {
        /**
         * test save
         */
        $post = $this->app['post.model']->create(['title' => 'title', 'slug' => 'slug']);
        $post->save();
        $post2 = $this->app['post.model']->create(['title' => 'title1', 'slug' => 'slug2']);
        $post2->save();
        $post3 = $this->app['post.model']->create(['title' => 'title44', 'slug' => 'slug2']);
        $post3->save();

        $this->assertEquals(2, $post2->id);

        /**
         * test update
         */
        $post2->slug = 99;
        $result_id = $post2->save();
        $this->assertEquals(1,$result_id);

        /**
         * find where
         */
        $db_post_where = $this->app['post.model']->where(['id >='=> 2])->all();
        $this->assertCount(2, $db_post_where);

        /**
         * find by id
         */
        $db_post = $this->app['post.model']->find(3);
        $this->assertEquals($post3->title, $db_post->title);


        /**
         * find all
         */
        $db_post_all = $this->app['post.model']->all();
        $this->assertCount(3, $db_post_all);



        /**
         * delete
         */
        $delete_result = $post2->delete();
        $this->assertEquals(1, $delete_result);


    }


}
