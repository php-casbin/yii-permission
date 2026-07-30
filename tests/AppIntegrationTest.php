<?php

namespace Yii\Permission\Tests;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yii\Permission\Middleware\EnforcerMiddleware;
use Yii\Permission\Middleware\RequestMiddleware;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\MiddlewareFactory;
use Yiisoft\User\CurrentUser;

class AppIntegrationTest extends TestCase
{
    private Container $appContainer;
    private ResponseFactoryInterface $responseFactory;
    private ServerRequestFactoryInterface $requestFactory;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->responseFactory = $this->container->get(ResponseFactoryInterface::class);
        $this->requestFactory = $this->container->get(ServerRequestFactoryInterface::class);
        $currentUser = $this->createCurrentUser('alice');

        $diDefinitions = require dirname(__DIR__) . '/config/di.php';
        $psr17Definitions = require dirname(__DIR__) . '/vendor/yiisoft/app/config/web/di/psr17.php';

        $definitions = array_merge($psr17Definitions, $diDefinitions, [
            \Yiisoft\Db\Connection\ConnectionInterface::class => self::$dbConnection,
            CurrentUser::class => $currentUser,
        ]);

        $config = ContainerConfig::create()->withDefinitions($definitions);
        $this->appContainer = new Container($config);
    }

    public function testYiiAppMiddlewareDispatcherWithRequestMiddlewareAllowed(): void
    {
        $factory = new MiddlewareFactory($this->appContainer);
        $dispatcher = new MiddlewareDispatcher($factory);

        $dispatcher = $dispatcher->withMiddlewares([
            RequestMiddleware::class,
        ]);

        $request = $this->requestFactory->createServerRequest('read', 'data1');

        $fallbackHandler = new class ($this->responseFactory) implements RequestHandlerInterface {
            private ResponseFactoryInterface $rf;

            public function __construct(ResponseFactoryInterface $rf)
            {
                $this->rf = $rf;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = $this->rf->createResponse(200);
                $response->getBody()->write('App Action Reached');
                return $response;
            }
        };

        $response = $dispatcher->dispatch($request, $fallbackHandler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('App Action Reached', (string)$response->getBody());
    }

    public function testYiiAppMiddlewareDispatcherWithRequestMiddlewareDenied(): void
    {
        // Change current user to bob
        $bobUser = $this->createCurrentUser('bob');

        $diDefinitions = require dirname(__DIR__) . '/config/di.php';
        $psr17Definitions = require dirname(__DIR__) . '/vendor/yiisoft/app/config/web/di/psr17.php';

        $definitions = array_merge($psr17Definitions, $diDefinitions, [
            \Yiisoft\Db\Connection\ConnectionInterface::class => self::$dbConnection,
            CurrentUser::class => $bobUser,
        ]);

        $container = new Container(ContainerConfig::create()->withDefinitions($definitions));

        $factory = new MiddlewareFactory($container);
        $dispatcher = (new MiddlewareDispatcher($factory))->withMiddlewares([
            RequestMiddleware::class,
        ]);

        $request = $this->requestFactory->createServerRequest('read', 'data1');

        $fallbackHandler = new class ($this->responseFactory) implements RequestHandlerInterface {
            private ResponseFactoryInterface $rf;

            public function __construct(ResponseFactoryInterface $rf)
            {
                $this->rf = $rf;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = $this->rf->createResponse(200);
                $response->getBody()->write('App Action Reached');
                return $response;
            }
        };

        $response = $dispatcher->dispatch($request, $fallbackHandler);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Access denied', (string)$response->getBody());
    }
}
