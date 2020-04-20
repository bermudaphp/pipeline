<?php


namespace Lobster\Pipeline;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class Pipeline
 * @package Lobster\Pipeline
 */
final class Pipeline implements Contracts\Pipeline
{
    private Queue $queue;
    private RequestHandlerInterface $handler;

    /**
     * Pipeline constructor.
     * @param RequestHandlerInterface|null $handler
     */
    public function __construct(RequestHandlerInterface $handler = null)
    {
        $this->queue = new Queue();
        $this->handler = $handler ?? new EmptyHandler();
    }

    /**
     * @param MiddlewareInterface ...$middleware
     * @return Pipeline
     */
    public function pipe(MiddlewareInterface $middleware): Pipeline
    {
        $this->queue->enqueue($middleware);
        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return (new Next($this->queue, $handler))->handle($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface 
    {
        return $this->process($request, $this->handler);
    }
}
