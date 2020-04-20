<?php


namespace Lobster\Pipeline;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class Next
 * @package Lobster\Pipeline
 */
final class Next implements RequestHandlerInterface
{
    private Queue $queue;
    private RequestHandlerInterface $handler;

    /**
     * Next constructor.
     * @param Queue $queue
     * @param RequestHandlerInterface $handler
     */
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
        if (($m = $this->queue->dequeue()) != null)
        {
            return $m->process($request, $this);
        }

        return $this->handler->handle($request);
    }
}