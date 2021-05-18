<?php

namespace Bermuda\Pipeline;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Next
 * @package Bermuda\Pipeline
 */
final class Next implements RequestHandlerInterface
{
    private Queue $queue;
    private RequestHandlerInterface $handler;

    public function __construct(Queue $queue, RequestHandlerInterface $handler)
    {
        $this->queue = clone $queue;
        $this->handler = $handler;
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($middleware = $this->queue->dequeue()) != null ? $middleware->process($request, $this) : $this->handler->handle($request);
    }
}
