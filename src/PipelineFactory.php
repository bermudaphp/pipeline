<?php

namespace Bermuda\Http\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


final class PipelineFactory implements PipelineFactoryInterface
{
    public function createMiddlewarePipeline(iterable $middlewares = [], ?RequestHandlerInterface $fallbackHandler = null): PipelineInterface
    {
        return new Pipeline($middlewares, $fallbackHandler ?? new EmptyPipelineHandler);
    }
}
