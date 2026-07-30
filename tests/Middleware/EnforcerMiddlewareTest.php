<?php

namespace Yii\Permission\Tests\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yii\Permission\Middleware\EnforcerMiddleware;
use Yii\Permission\Tests\TestCase;
use Yiisoft\User\CurrentUser;

class TestRequestHandler implements RequestHandlerInterface
{
    private ResponseFactoryInterface $factory;

    public function __construct(ResponseFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->factory->createResponse(200);
        $response->getBody()->write('OK: Success Action');
        return $response;
    }
}

class EnforcerMiddlewareTest extends TestCase
{
    private ResponseInterface|RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $responseFactory = $this->container->get(\Psr\Http\Message\ResponseFactoryInterface::class);
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

    public function testEnforcerMiddlewareAllowedWithRealRequest(): void
    {
        $enforcer = $this->getEnforcer();
        $currentUser = $this->createCurrentUser('alice');
        $responseFactory = $this->container->get(\Psr\Http\Message\ResponseFactoryInterface::class);
        $requestFactory = $this->container->get(\Psr\Http\Message\ServerRequestFactoryInterface::class);

        $middleware = new EnforcerMiddleware(
            $enforcer,
            $responseFactory,
            ['data1', 'read'],
            $currentUser
        );

        $request = $requestFactory->createServerRequest('GET', '/articles');

        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK: Success Action', (string)$response->getBody());
    }

    public function testEnforcerMiddlewareDeniedWithRealRequest(): void
    {
        $enforcer = $this->getEnforcer();
        $currentUser = $this->createCurrentUser('bob');
        $responseFactory = $this->container->get(\Psr\Http\Message\ResponseFactoryInterface::class);
        $requestFactory = $this->container->get(\Psr\Http\Message\ServerRequestFactoryInterface::class);

        $middleware = new EnforcerMiddleware(
            $enforcer,
            $responseFactory,
            ['data1', 'read'],
            $currentUser
        );

        $request = $requestFactory->createServerRequest('GET', '/articles');

        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Access denied', (string)$response->getBody());
    }
}
