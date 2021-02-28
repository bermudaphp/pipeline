<?php

namespace Bermuda\Pipeline;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Pipeline
 * @package Bermuda\Pipeline
 */
final class Pipeline implements PipelineInterface
{
    private Queue $queue;
    private RequestHandlerInterface $handler;

    /**
     * Pipeline constructor.
     * @param RequestHandlerInterface|null $fallbackHandler
     */
    public function __construct(?RequestHandlerInterface $fallbackHandler = null)
    {
        $this->queue = new Queue();
        $this->handler = $fallbackHandler ?? new EmptyPipelineHandler();
    }

    /**
     * @param MiddlewareInterface $middleware
     * @return Pipeline
     */
    public function pipe(MiddlewareInterface $middleware): PipelineInterface
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

    /**
     * @param MiddlewareInterface[] $middleware
     * @param RequestHandlerInterface|null $fallbackHandler
     * @return self
     */
    public static function makeOf(iterable $middleware, ?RequestHandlerInterface $fallbackHandler = null): self
    {
        $self = new self($fallbackHandler);
        
        foreach ($middleware as $item)
        {
            $self->pipe($item);
        }
        
        return $self;
    }
}
