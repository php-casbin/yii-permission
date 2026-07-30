<?php

namespace Yii\Permission\Tests\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yii\Permission\Middleware\RequestMiddleware;
use Yii\Permission\Tests\TestCase;
use Yiisoft\User\CurrentUser;

class RequestMiddlewareTest extends TestCase
{
    private TestRequestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        $this->handler = new TestRequestHandler($responseFactory);
    }

    private function createCurrentUser(?string $id): CurrentUser
    {
        $identityRepository = $this->createMock(\Yiisoft\Auth\IdentityRepositoryInterface::class);
        $eventDispatcher = $this->createMock(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(fn($event) => $event);

        $currentUser = new CurrentUser($identityRepository, $eventDispatcher);

        if ($id !== null) {
            $identity = $this->createMock(\Yiisoft\Auth\IdentityInterface::class);
            $identity->method('getId')->willReturn($id);
            $currentUser->login($identity);
        }

        return $currentUser;
    }

    public function testRequestMiddlewareAllowedWithRealRequest(): void
    {
        $enforcer = $this->getEnforcer();
        $currentUser = $this->createCurrentUser('alice');
        $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        $requestFactory = $this->container->get(ServerRequestFactoryInterface::class);

        $middleware = new RequestMiddleware(
            $enforcer,
            $responseFactory,
            $currentUser
        );

        // Path: data1, Method: read (alice has permission)
        $request = $requestFactory->createServerRequest('read', 'data1');

        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK: Success Action', (string)$response->getBody());
    }

    public function testRequestMiddlewareDeniedWithRealRequest(): void
    {
        $enforcer = $this->getEnforcer();
        $currentUser = $this->createCurrentUser('bob');
        $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        $requestFactory = $this->container->get(ServerRequestFactoryInterface::class);

        $middleware = new RequestMiddleware(
            $enforcer,
            $responseFactory,
            $currentUser
        );

        // Path: data1, Method: read (bob has no permission)
        $request = $requestFactory->createServerRequest('read', 'data1');

        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Access denied', (string)$response->getBody());
    }

    public function testRequestMiddlewareFallbackToAttributeUserId(): void
    {
        $enforcer = $this->getEnforcer();
        $currentUser = $this->createCurrentUser(null);
        $responseFactory = $this->container->get(ResponseFactoryInterface::class);
        $requestFactory = $this->container->get(ServerRequestFactoryInterface::class);

        $middleware = new RequestMiddleware(
            $enforcer,
            $responseFactory,
            $currentUser
        );

        // Request has attribute user_id = alice
        $request = $requestFactory->createServerRequest('read', 'data1')
            ->withAttribute('user_id', 'alice');

        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK: Success Action', (string)$response->getBody());
    }
}
