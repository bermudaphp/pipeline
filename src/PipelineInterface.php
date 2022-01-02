<?php

namespace Bermuda\Pipeline;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Interface PipelineInterface
 * @package Bermuda\Pipeline
 */
interface PipelineInterface extends MiddlewareInterface, RequestHandlerInterface
{
    /**
     * @param MiddlewareInterface $middleware
     * @return PipelineInterface
     */
    public function pipe(MiddlewareInterface $middleware): PipelineInterface ;
}
