<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Alternative test middleware class for negative test cases.
 * 
 * This class is used in tests to verify that the Pipeline::has() method
 * returns false when searching for middleware that is not present in the pipeline.
 * It serves as a control case to ensure the has() method doesn't produce false positives.
 */
final class AnotherTestMiddleware implements MiddlewareInterface
{
    /**
     * Processes an incoming server request and returns a response.
     * 
     * This is a pass-through implementation that immediately delegates
     * to the next handler in the chain without any modifications.
     *
     * @param ServerRequestInterface $request The incoming server request.
     * @param RequestHandlerInterface $handler The next handler in the chain.
     * @return ResponseInterface The response from the next handler.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        return $handler->handle($request);
    }
}
