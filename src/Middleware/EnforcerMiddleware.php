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
 * Basic Enforcer Middleware for Casbin authorization.
 * Checks explicit permission parameters (e.g. resource & action).
 */
class EnforcerMiddleware implements MiddlewareInterface
{
    private Enforcer $enforcer;
    private array $params;
    private ?CurrentUser $currentUser;
    private ResponseFactoryInterface $responseFactory;

    /**
     * @param Enforcer $enforcer Casbin Enforcer instance
     * @param ResponseFactoryInterface $responseFactory Response factory for 403 responses
     * @param array $params Explicit parameters passed to enforce(), e.g. ['articles', 'read']
     * @param CurrentUser|null $currentUser User identity component (e.g. Yii3 CurrentUser)
     */
    public function __construct(
        Enforcer $enforcer,
        ResponseFactoryInterface $responseFactory,
        array $params = [],
        ?CurrentUser $currentUser = null
    ) {
        $this->enforcer = $enforcer;
        $this->responseFactory = $responseFactory;
        $this->params = $params;
        $this->currentUser = $currentUser;
    }

    /**
     * Returns a new instance with the specified permission parameters.
     *
     * @param array $params Permission parameters, e.g. ['articles', 'read']
     * @return self
     */
    public function withParams(array $params): self
    {
        $new = clone $this;
        $new->params = $params;
        return $new;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $this->currentUser?->getId() ?? $request->getAttribute('user_id', 'guest');

        $enforceParams = array_merge([$userId], $this->params);

        if (!$this->enforcer->enforce(...$enforceParams)) {
            $response = $this->responseFactory->createResponse(403);
            $response->getBody()->write('Access denied');
            return $response;
        }

        return $handler->handle($request);
    }
}
