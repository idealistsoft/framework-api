<?php

namespace Infuse\RestApi\Tests\Route;

use Pulsar\Driver\DriverInterface;
use Infuse\Request;
use Infuse\Response;
use Infuse\RestApi\Error\ApiError;
use Infuse\RestApi\Error\InvalidRequest;
use Infuse\RestApi\Route\EditModelRoute;
use Infuse\RestApi\Tests\Book;
use Infuse\RestApi\Tests\Person;
use Infuse\RestApi\Tests\Post;
use Mockery;

class EditModelRouteTest extends ModelTestBase
{
    const ROUTE_CLASS = EditModelRoute::class;

    public function testParseModelId()
    {
        $req = new Request();
        $req->setParams(['model_id' => 1]);
        $route = $this->getRoute($req);
        $this->assertEquals([1], $route->getModelId());
    }

    public function testGetUpdateParameters()
    {
        $req = new Request([], ['test' => true]);
        $route = $this->getRoute($req);

        $expected = [
            'test' => true,
        ];
        $this->assertEquals($expected, $route->getUpdateParameters());
    }

    public function testBuildResponse()
    {
        $model = new Person(100);
        $model = Mockery::mock($model);
        $model->refreshWith(['name' => 'Bob']);
        $model->shouldReceive('set')
              ->andReturn(true);
        $route = $this->getRoute();
        $route->setModel($model);

        $this->assertEquals($model, $route->buildResponse());
    }

    public function testBuildResponseNotFound()
    {
        $driver = Mockery::mock(DriverInterface::class);
        $driver->shouldReceive('queryModels')
               ->andReturn([]);
        Person::setDriver($driver);

        $model = Person::class;
        $route = $this->getRoute();
        $route->setModelId(100)
              ->setModel($model);

        try {
            $route->buildResponse();
        } catch (InvalidRequest $e) {
        }

        $this->assertEquals('Person was not found: 100', $e->getMessage());
        $this->assertEquals(404, $e->getHttpStatus());
    }

    public function testBuildResponseSetFail()
    {
        $driver = Mockery::mock(DriverInterface::class);
        $driver->shouldReceive('queryModels')
               ->andReturn([['id' => 1]]);
        $driver->shouldReceive('updateModel')
               ->andReturn(false);
        Post::setDriver($driver);

        $model = Post::class;
        $route = $this->getRoute();
        $route->setModel($model)
              ->setModelId(1)
              ->setUpdateParameters(['test' => true]);

        try {
            $route->buildResponse();
        } catch (ApiError $e) {
        }

        $this->assertEquals('There was an error updating the Post.', $e->getMessage());
    }

    public function testBuildResponseValidationError()
    {
        $model = new Person(100);
        $model = Mockery::mock($model);
        $model->refreshWith(['name' => 'Bob']);
        $model->shouldReceive('set')
              ->andReturn(false);
        $route = $this->getRoute();
        $route->setModel($model);
        $model->getErrors()->add('error');

        try {
            $route->buildResponse();
        } catch (InvalidRequest $e) {
        }

        $this->assertEquals('error', $e->getMessage());
        $this->assertEquals(400, $e->getHttpStatus());
    }

    public function testBuildResponseMassAssignmentError()
    {
        $req = Request::create('/', 'POST', ['not_allowed' => true]);
        $route = $this->getRoute($req);
        $model = new Book(100);
        $model = Mockery::mock($model);
        $model->refreshWith(['name' => 'Bob']);
        $route->setModel($model);

        try {
            $route->buildResponse();
            $this->fail('buildResponse() should have returned an exception');
        } catch (InvalidRequest $e) {
        }

        $this->assertEquals('Mass assignment of not_allowed on Book is not allowed', $e->getMessage());
        $this->assertEquals(400, $e->getHttpStatus());
    }

    public function testInvalidRequestBody()
    {
        $this->expectException(InvalidRequest::class);

        $req = Mockery::mock(new Request());
        $req->shouldReceive('request')
            ->andReturn('invalid');

        $res = new Response();

        $route = new EditModelRoute($req, $res);
        $route->setModel(Post::class);

        $route->buildResponse();
    }
}
