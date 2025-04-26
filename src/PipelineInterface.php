<?php

namespace Bermuda\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface PipelineInterface extends MiddlewareInterface, RequestHandlerInterface
{
    /**
     * Adds middleware to the pipeline.
     *
     * Accepts either a single MiddlewareInterface instance or an iterable collection of middleware.
     * A cloned pipeline instance is created and the provided middleware are appended,
     * ensuring that the original pipeline remains immutable.
     *
     * @param iterable<MiddlewareInterface>|MiddlewareInterface $middlewares A middleware or collection of middleware objects.
     *
     * @return PipelineInterface Returns a new pipeline instance with the added middleware.
     *
     * @throws \InvalidArgumentException If any provided element does not implement MiddlewareInterface.
     *         Error Message: "Provided middleware (index) does not implement MiddlewareInterface"
     * @throws \RuntimeException If the middleware being added is the pipeline itself.
     */
    public function pipe(iterable|MiddlewareInterface $middlewares): PipelineInterface ;
}
