<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test middleware class used for verifying class-based middleware detection.
 * 
 * This class is used in tests to verify that the Pipeline::has() method
 * can correctly identify middleware by their fully-qualified class name.
 * It implements pass-through behavior, delegating all requests to the next handler.
 */
final class TestMiddleware implements MiddlewareInterface
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
