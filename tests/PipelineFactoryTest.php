<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use Bermuda\Http\Middleware\PipelineFactory;
use Bermuda\Http\Middleware\PipelineFactoryInterface;
use Bermuda\Http\Middleware\PipelineInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test suite for PipelineFactory.
 * 
 * Validates that the factory correctly creates Pipeline instances with:
 * - Various middleware configurations
 * - Custom and default fallback handlers
 * - Different iterable types (array, generator)
 * - Proper interface compliance
 * 
 * These tests ensure the factory serves as a reliable dependency injection
 * point for creating pipelines throughout an application.
 */
final class PipelineFactoryTest extends TestCase
{
    private PipelineFactoryInterface $factory;
    private ServerRequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->factory = new PipelineFactory();
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    #[Test]
    public function itImplementsPipelineFactoryInterface(): void
    {
        $this->assertInstanceOf(PipelineFactoryInterface::class, $this->factory);
    }

    #[Test]
    public function itCreatesEmptyPipeline(): void
    {
        $pipeline = $this->factory->createMiddlewarePipeline();

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertTrue($pipeline->isEmpty());
        $this->assertSame(0, $pipeline->count());
    }

    #[Test]
    public function itCreatesPipelineWithMiddlewares(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $pipeline = $this->factory->createMiddlewarePipeline([
            $middleware1,
            $middleware2,
            $middleware3,
        ]);

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertFalse($pipeline->isEmpty());
        $this->assertSame(3, $pipeline->count());
        $this->assertTrue($pipeline->has($middleware1));
        $this->assertTrue($pipeline->has($middleware2));
        $this->assertTrue($pipeline->has($middleware3));
    }

    #[Test]
    public function itCreatesPipelineWithCustomHandler(): void
    {
        $executionOrder = [];

        $middleware = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'middleware';
            return $handler->handle($request);
        });

        $customHandler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'custom-handler';
            return $this->response;
        });

        $pipeline = $this->factory->createMiddlewarePipeline(
            [$middleware],
            $customHandler
        );

        $response = $pipeline->handle($this->request);

        $this->assertSame($this->response, $response);
        $this->assertSame(['middleware', 'custom-handler'], $executionOrder);
    }

    #[Test]
    public function itCreatesPipelineWithDefaultHandler(): void
    {
        $pipeline = $this->factory->createMiddlewarePipeline();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to process the request. The pipeline is empty!');

        $pipeline->handle($this->request);
    }

    #[Test]
    public function itCreatesPipelineFromGenerator(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $generator = function () use ($middleware1, $middleware2) {
            yield $middleware1;
            yield $middleware2;
        };

        $pipeline = $this->factory->createMiddlewarePipeline($generator());

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertSame(2, $pipeline->count());
        $this->assertTrue($pipeline->has($middleware1));
        $this->assertTrue($pipeline->has($middleware2));
    }

    #[Test]
    public function itCreatesIndependentPipelineInstances(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $pipeline1 = $this->factory->createMiddlewarePipeline([$middleware1]);
        $pipeline2 = $this->factory->createMiddlewarePipeline([$middleware2]);

        $this->assertNotSame($pipeline1, $pipeline2);
        $this->assertSame(1, $pipeline1->count());
        $this->assertSame(1, $pipeline2->count());
        $this->assertTrue($pipeline1->has($middleware1));
        $this->assertFalse($pipeline1->has($middleware2));
        $this->assertTrue($pipeline2->has($middleware2));
        $this->assertFalse($pipeline2->has($middleware1));
    }

    #[Test]
    public function itThrowsExceptionWhenCreatingPipelineWithInvalidMiddleware(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware at position 0 must implement');

        $this->factory->createMiddlewarePipeline(['not a middleware']);
    }

    #[Test]
    public function itCreatesFunctionalPipeline(): void
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

        $handler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return $this->response;
        });

        $pipeline = $this->factory->createMiddlewarePipeline(
            [$middleware1, $middleware2, $middleware3],
            $handler
        );

        $response = $pipeline->handle($this->request);

        $this->assertSame($this->response, $response);
        $this->assertSame([1, 2, 3, 'handler'], $executionOrder);
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
