<?php


namespace Lobster\Pipeline\Contracts;


use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Interface PipelineFactory
 * @package Lobster\Pipeline\Contracts
 */
interface PipelineFactory
{

    /**
     * @param MiddlewareInterface[] $middleware
     * @param RequestHandlerInterface|null $handler
     * @return Pipeline
     */
    public function __invoke(array $middleware = [], RequestHandlerInterface $handler = null): Pipeline ;
}