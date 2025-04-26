<?php

namespace Bermuda\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface PipelineFactoryInterface
{
    public function createMiddlewarePipeline(iterable $middlewares = [], ?RequestHandlerInterface $fallbackHandler = null): PipelineInterface ;
}
