<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use Bermuda\Http\Middleware\Next;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;

/**
 * Test suite for the Next handler class.
 * 
 * Validates the internal queue-based middleware processor that powers
 * the Pipeline's execution mechanism. These tests ensure:
 * - Proper sequential execution through the middleware queue
 * - Queue isolation (cloning prevents side effects)
 * - Correct delegation to the final handler when queue is exhausted
 * - Support for short-circuit responses from middleware
 * 
 * This is an internal class but critical for pipeline correctness.
 */
final class NextTest extends TestCase
{
    private ServerRequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    #[Test]
    public function itCallsFinalHandlerWhenQueueIsEmpty(): void
    {
        $queue = new SplQueue();
        $finalHandler = $this->createMock(RequestHandlerInterface::class);

        $finalHandler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $next = new Next($queue, $finalHandler);
        $result = $next->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    #[Test]
    public function itProcessesMiddlewareFromQueue(): void
    {
        $executionOrder = [];

        $middleware = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'middleware';
            return $handler->handle($request);
        });

        $finalHandler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return $this->response;
        });

        $queue = new SplQueue();
        $queue->enqueue($middleware);

        $next = new Next($queue, $finalHandler);
        $result = $next->handle($this->request);

        $this->assertSame($this->response, $result);
        $this->assertSame(['middleware', 'handler'], $executionOrder);
    }

    #[Test]
    public function itProcessesMultipleMiddlewaresInSequence(): void
    {
        $executionOrder = [];

        $middleware1 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 1;
            return $handler->handle($request);
        });

        $middleware2 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 2;
            return $handler->handle($request);
        });

        $middleware3 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 3;
            return $handler->handle($request);
        });

        $finalHandler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return $this->response;
        });

        $queue = new SplQueue();
        $queue->enqueue($middleware1);
        $queue->enqueue($middleware2);
        $queue->enqueue($middleware3);

        $next = new Next($queue, $finalHandler);
        $result = $next->handle($this->request);

        $this->assertSame($this->response, $result);
        $this->assertSame([1, 2, 3, 'handler'], $executionOrder);
    }

    #[Test]
    public function itClonesQueueToPreventSideEffects(): void
    {
        $middleware = $this->createMiddleware(function ($request, $handler) {
            return $handler->handle($request);
        });

        $finalHandler = $this->createHandler(function () {
            return $this->response;
        });

        $queue = new SplQueue();
        $queue->enqueue($middleware);

        $originalCount = $queue->count();

        $next = new Next($queue, $finalHandler);
        $next->handle($this->request);

        $this->assertSame($originalCount, $queue->count());
    }

    #[Test]
    public function itAllowsMiddlewareToShortCircuit(): void
    {
        $executionOrder = [];
        $shortCircuitResponse = $this->createMock(ResponseInterface::class);

        $middleware1 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 1;
            return $handler->handle($request);
        });

        $middleware2 = $this->createMiddleware(function () use (&$executionOrder, $shortCircuitResponse) {
            $executionOrder[] = 2;
            return $shortCircuitResponse;
        });

        $middleware3 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 3;
            return $handler->handle($request);
        });

        $finalHandler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return $this->response;
        });

        $queue = new SplQueue();
        $queue->enqueue($middleware1);
        $queue->enqueue($middleware2);
        $queue->enqueue($middleware3);

        $next = new Next($queue, $finalHandler);
        $result = $next->handle($this->request);

        $this->assertSame($shortCircuitResponse, $result);
        $this->assertSame([1, 2], $executionOrder);
    }

    /**
     * Creates a test middleware with custom callback logic.
     *
     * @param callable $callback The callback to execute when middleware processes a request.
     * @return MiddlewareInterface A middleware instance that delegates to the callback.
     */
    private function createMiddleware(callable $callback): MiddlewareInterface
    {
        return new class($callback) implements MiddlewareInterface {
            public function __construct(private $callback) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return ($this->callback)($request, $handler);
            }
        };
    }

    /**
     * Creates a test request handler with custom callback logic.
     *
     * @param callable $callback The callback to execute when handler processes a request.
     * @return RequestHandlerInterface A handler instance that delegates to the callback.
     */
    private function createHandler(callable $callback): RequestHandlerInterface
    {
        return new class($callback) implements RequestHandlerInterface {
            public function __construct(private $callback) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->callback)($request);
            }
        };
    }
}
