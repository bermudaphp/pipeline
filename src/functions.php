<?php

namespace Bermuda\Http\Middleware;

/**
 * @param MiddlewareInterface[] $middleware
 * @param RequestHandlerInterface|null $fallbackHandler
 * @return Pipeline
 */
function pipe(iterable $middleware = [], ?RequestHandlerInterface $fallbackHandler = null): Pipeline
{
    return Pipeline::createFromIterable($middleware, $fallbackHandler);
}
