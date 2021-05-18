<?php

namespace Bermuda\Pipeline;

/**
 * @param MiddlewareInterface[] $middleware
 * @param RequestHandlerInterface|null $fallbackHandler
 * @return Pipeline
 */
function pipe(iterable $middleware = [], ?RequestHandlerInterface $fallbackHandler = null): Pipeline
{
    return Pipeline::makeOf($middleware, $fallbackHandler);
}
