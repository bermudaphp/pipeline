<?php

namespace Bermuda\Pipeline;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Interface PipelineFactoryInterface
 * @package Bermuda\Pipeline
 */
interface PipelineFactoryInterface
{
    /**
     * @param MiddlewareInterface[] $middleware
     * @param RequestHandlerInterface|null $handler
     * @return PipelineInterface
     */
    public function make(?array $middleware = [], RequestHandlerInterface $handler = null): PipelineInterface ;
}
