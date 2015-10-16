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
            'post.model' => function () use ($app) {
                return new Post($app);;
            },
            'comment.model' => 'Comment',
        ];

        $app['db'] = function () use ($db_config) {
            $db = \Doctrine\DBAL\DriverManager::getConnection($db_config);
            $sql = file_get_contents(codecept_data_dir() . '/dump.sql');
            $db->exec($sql);
            return $db;
        };

        foreach ($models as $name => $class) {
            if (is_callable($class)) {
                $callable = $class;
            } else {
                $callable = function () use ($class, $app) {
                    return new $class($app);
                };
            }
            $app[$name] = $app->factory($callable);
        }
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
        $this->assertInstanceOf('Pimple\Container', $this->app['post.model']->app());
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

    public function testFindIds()
    {
        $posts = $this->app['post.model']->find([1, 2]);
        $this->assertCount(2, $posts);
    }


    public function testFindOrFailSuccess()
    {
        $post = $this->app['post.model']->findOrFail(2);
        $this->assertEquals(2, $post->id);
        $this->assertEquals('title2', $post->title);
        $this->assertEquals('slug2', $post->slug);
    }

    public function testFindOrFailIdsSuccess()
    {
        $posts = $this->app['post.model']->findOrFail([1, 2]);
        $this->assertCount(2, $posts);
    }

    /**
     * @expectedException \Record\ModelNotFoundException
     */
    public function testFindOrFail()
    {
        $this->app['post.model']->findOrFail(100);
    }

    /**
     * @expectedException \Record\ModelNotFoundException
     */
    public function testFindOrFailIds()
    {
        $this->app['post.model']->findOrFail([1, 100]);
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
        $posts = $this->app['post.model']->where('id', '>=', 2)->get();
        $this->assertCount(2, $posts);
    }

    public function testDelete()
    {
        $delete_result = $this->app['post.model']->find(1)->delete();
        $this->assertEquals(1, $delete_result);
    }

    public function testDeleteWhere()
    {
        $delete_result = $this->app['post.model']->where('id', [1, 2])->delete();
        $this->assertEquals(2, $delete_result);
    }

    public function testDestroyId()
    {
        $delete_result = $this->app['post.model']->destroy(1);
        $this->assertEquals(1, $delete_result);
    }

    public function testDestroyIds()
    {
        $delete_result = $this->app['post.model']->destroy(1, 2);
        $this->assertEquals(2, $delete_result);
    }

    public function testSkipTake()
    {
        $sql = $this->app['post.model']->skip(1)->take(1)->getSql();
        $this->assertEquals($sql, 'SELECT * FROM post LIMIT 1 OFFSET 1');
    }

    public function testWhereDoctrine()
    {
        $sql = $this->app['post.model']->where('id = :id')->setParameter(':id', 2)->getSql();
        $this->assertEquals($sql, 'SELECT * FROM post WHERE id = :id');
    }

    public function testOrWhere()
    {
        $sql = $this->app['post.model']->where('id', '=', 100)->where('id', '=', 2, 'or')->getSql();
        $this->assertEquals($sql, 'SELECT * FROM post WHERE (id=:dcValue1) OR (id=:dcValue2)');

        $sql = $this->app['post.model']->orWhere('id', '=', 100)->orWhere('id', '=', 2)->getSql();
        $this->assertEquals($sql, 'SELECT * FROM post WHERE (id=:dcValue1) OR (id=:dcValue2)');
    }

    public function testScope()
    {
        $query = $this->app['post.model']->title('title2');
        $sql = $query->getSql();
        $params = $query->getParameters();
        $this->assertEquals($sql, 'SELECT * FROM post WHERE title=:dcValue1');
        $this->assertEquals($params['dcValue1'], 'title2');
    }

    public function testScopeFail()
    {
        $not_found_method = $this->app['post.model']->notFound();
        $this->assertSame($not_found_method, null);
    }
}
