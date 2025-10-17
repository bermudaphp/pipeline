<?php

namespace Bermuda\Http\Middleware;

use \SplQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handler class that processes the next middleware in the queue.
 * 
 * This class manages the sequential processing of middleware using a queue,
 * eliminating the need for position tracking.
 *
 * @internal This class is for internal use only.
 */
final class Next implements RequestHandlerInterface
{
    private SplQueue $queue;
  
    /**
     * @param \SplQueue<MiddlewareInterface> $queue Queue of middleware
     * @param RequestHandlerInterface $finalHandler Handler to use when queue is empty
     */
    public function __construct(
        SplQueue $queue,
        private readonly RequestHandlerInterface $finalHandler
    ) {
      $this->queue = clone $queue;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->queue->isEmpty()) {
            return $this->finalHandler->handle($request);
        }

        $middleware = $this->queue->dequeue();
        return $middleware->process($request, $this);
    }
}
