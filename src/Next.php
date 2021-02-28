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
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (($middleware = $this->queue->dequeue()) != null)
        {
            return $middleware->process($request, $this);
        }

        return $this->handler->handle($request);
    }
}
