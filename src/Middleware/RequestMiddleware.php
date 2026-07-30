<?php

namespace Yii\Permission\Middleware;

use Casbin\Enforcer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\User\CurrentUser;

/**
 * HTTP Request Middleware for RESTful Casbin authorization.
 * Automatically extracts Request Path and Method as policy parameters.
 */
class RequestMiddleware implements MiddlewareInterface
{
    private Enforcer $enforcer;
    private ?CurrentUser $currentUser;
    private ResponseFactoryInterface $responseFactory;

    /**
     * @param Enforcer $enforcer Casbin Enforcer instance
     * @param ResponseFactoryInterface $responseFactory Response factory for 403 responses
     * @param CurrentUser|null $currentUser User identity component (e.g. Yii3 CurrentUser)
     */
    public function __construct(
        Enforcer $enforcer,
        ResponseFactoryInterface $responseFactory,
        ?CurrentUser $currentUser = null
    ) {
        $this->enforcer = $enforcer;
        $this->responseFactory = $responseFactory;
        $this->currentUser = $currentUser;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $this->currentUser?->getId() ?? $request->getAttribute('user_id', 'guest');
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        if (!$this->enforcer->enforce($userId, $path, $method)) {
            $response = $this->responseFactory->createResponse(403);
            $response->getBody()->write('Access denied');
            return $response;
        }

        return $handler->handle($request);
    }
}
