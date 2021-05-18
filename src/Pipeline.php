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
    
    public function __clone()
    {
        $this->queue = clone $this->queue;
        $this->handler = clone $this->handler;
    }

    public function __construct(?RequestHandlerInterface $fallbackHandler = null)
    {
        $this->queue = new Queue();
        $this->handler = $fallbackHandler ?? new EmptyPipelineHandler();
    }
   
    /**
     * @inheritDoc
     */
    public function pipe(MiddlewareInterface $middleware): PipelineInterface
    {
        $this->queue->enqueue($middleware);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return (new Next($this->queue, $handler))->handle($request);
    }

    /**
     * @inheritDoc
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
    public static function makeOf(iterable $middleware = [], ?RequestHandlerInterface $fallbackHandler = null): self
    {
        $pipeline = new self($fallbackHandler);
        
        foreach ($middleware as $item)
        {
            $pipeline->pipe($item);
        }
        
        return $pipeline;
    }
    
    public function fallbackHandler(?RequestHandlerInterface $handler = null):? RequestHandlerInterface
    {
        return $handler != null ? $this->handler = $handler : $this->handler;
    }
}
