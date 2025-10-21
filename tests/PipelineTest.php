<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use Bermuda\Http\Middleware\Pipeline;
use Bermuda\Http\Middleware\PipelineInterface;
use Bermuda\Http\Middleware\EmptyPipelineHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Comprehensive test suite for the Pipeline middleware implementation.
 * 
 * Tests cover critical functionality:
 * - Pipeline construction and initialization
 * - Middleware execution order (essential for correctness)
 * - Immutability guarantees (prevents bugs in concurrent scenarios)
 * - Circular reference detection (prevents infinite loops)
 * - Error handling and validation (ensures type safety)
 * - PSR-15 compliance (interface contract verification)
 * 
 * Each test validates real behavior without mocking expectations,
 * focusing on detecting actual bugs rather than verifying implementation details.
 */
final class PipelineTest extends TestCase
{
    private ServerRequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    #[Test]
    public function itCreatesEmptyPipeline(): void
    {
        $pipeline = new Pipeline();

        $this->assertTrue($pipeline->isEmpty());
        $this->assertSame(0, $pipeline->count());
    }

    #[Test]
    public function itCreatesPipelineWithMiddlewares(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1, $middleware2]);

        $this->assertFalse($pipeline->isEmpty());
        $this->assertSame(2, $pipeline->count());
    }

    #[Test]
    public function itThrowsExceptionWhenEmptyPipelineIsHandled(): void
    {
        $pipeline = new Pipeline();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to process the request. The pipeline is empty!');

        $pipeline->handle($this->request);
    }

    #[Test]
    public function itExecutesMiddlewaresInCorrectOrder(): void
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

        $pipeline = new Pipeline([$middleware1, $middleware2, $middleware3], $handler);
        $response = $pipeline->handle($this->request);

        $this->assertSame($this->response, $response);
        $this->assertSame([1, 2, 3, 'handler'], $executionOrder);
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

        $handler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return $this->response;
        });

        $pipeline = new Pipeline([$middleware1, $middleware2, $middleware3], $handler);
        $response = $pipeline->handle($this->request);

        $this->assertSame($shortCircuitResponse, $response);
        $this->assertSame([1, 2], $executionOrder);
    }

    #[Test]
    public function itPipesMiddlewareAtTheEnd(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1]);
        $newPipeline = $pipeline->pipe([$middleware2, $middleware3]);

        $this->assertSame(1, $pipeline->count());
        $this->assertSame(3, $newPipeline->count());

        $middlewares = iterator_to_array($newPipeline);
        $this->assertSame($middleware1, $middlewares[0]);
        $this->assertSame($middleware2, $middlewares[1]);
        $this->assertSame($middleware3, $middlewares[2]);
    }

    #[Test]
    public function itPipesMiddlewareAtTheBeginning(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1]);
        $newPipeline = $pipeline->pipe([$middleware2, $middleware3], prepend: true);

        $this->assertSame(3, $newPipeline->count());

        $middlewares = iterator_to_array($newPipeline);
        $this->assertSame($middleware2, $middlewares[0]);
        $this->assertSame($middleware3, $middlewares[1]);
        $this->assertSame($middleware1, $middlewares[2]);
    }

    #[Test]
    public function itPipesSingleMiddleware(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1]);
        $newPipeline = $pipeline->pipe($middleware2);

        $this->assertSame(2, $newPipeline->count());
        $this->assertTrue($newPipeline->has($middleware1));
        $this->assertTrue($newPipeline->has($middleware2));
    }

    #[Test]
    public function itThrowsExceptionWhenPipingInvalidMiddleware(): void
    {
        $pipeline = new Pipeline();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware at position 0 must implement');

        $pipeline->pipe(['not a middleware']);
    }

    #[Test]
    public function itThrowsExceptionWhenConstructingWithInvalidMiddleware(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Middleware at position 1 must implement');

        new Pipeline([
            $this->createMock(MiddlewareInterface::class),
            'not a middleware',
        ]);
    }

    #[Test]
    public function itPreventsCircularReferenceToItself(): void
    {
        $pipeline = new Pipeline();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add pipeline to itself - this would create circular reference');

        $pipeline->pipe($pipeline);
    }

    #[Test]
    public function itPreventsCircularReferenceThroughNestedPipeline(): void
    {
        $pipeline1 = new Pipeline();
        $pipeline2 = new Pipeline();

        $pipeline1 = $pipeline1->pipe($pipeline2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add pipeline that contains reference to this pipeline');

        $pipeline2->pipe($pipeline1);
    }

    #[Test]
    public function itChecksMiddlewareExistenceByInstance(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1]);

        $this->assertTrue($pipeline->has($middleware1));
        $this->assertFalse($pipeline->has($middleware2));
    }

    #[Test]
    public function itChecksMiddlewareExistenceByClassName(): void
    {
        $middleware = new TestMiddleware();
        $pipeline = new Pipeline([$middleware]);

        $this->assertTrue($pipeline->has(TestMiddleware::class));
        $this->assertFalse($pipeline->has(AnotherTestMiddleware::class));
    }

    #[Test]
    public function itIteratesOverMiddlewares(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1, $middleware2, $middleware3]);

        $result = [];
        foreach ($pipeline as $middleware) {
            $result[] = $middleware;
        }

        $this->assertSame([$middleware1, $middleware2, $middleware3], $result);
    }

    #[Test]
    public function itProcessesWithCustomHandler(): void
    {
        $executionOrder = [];
        $customResponse = $this->createMock(ResponseInterface::class);

        $middleware = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'middleware';
            return $handler->handle($request);
        });

        $customHandler = $this->createHandler(function () use (&$executionOrder, $customResponse) {
            $executionOrder[] = 'custom-handler';
            return $customResponse;
        });

        $defaultHandler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'default-handler';
            return $this->response;
        });

        $pipeline = new Pipeline([$middleware], $defaultHandler);
        $response = $pipeline->process($this->request, $customHandler);

        $this->assertSame($customResponse, $response);
        $this->assertSame(['middleware', 'custom-handler'], $executionOrder);
    }

    #[Test]
    public function itThrowsExceptionWhenProcessingWithItselfAsHandler(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $pipeline = new Pipeline([$middleware]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot use pipeline as its own handler - this would cause all middleware to execute twice');

        $pipeline->process($this->request, $pipeline);
    }

    #[Test]
    public function itClonesPipelineCorrectly(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $pipeline = new Pipeline([$middleware], $handler);
        $clone = clone $pipeline;

        $this->assertSame($pipeline->count(), $clone->count());
        $this->assertNotSame($pipeline, $clone);
    }

    #[Test]
    public function itCreatesPipelineFromIterable(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $pipeline = Pipeline::createFromIterable([$middleware1, $middleware2], $handler);

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertSame(2, $pipeline->count());
    }

    #[Test]
    public function itCreatesPipelineFromIterableWithDefaultHandler(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $pipeline = Pipeline::createFromIterable([$middleware]);

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertSame(1, $pipeline->count());
    }

    #[Test]
    public function itUpdatesFallbackHandler(): void
    {
        $executionOrder = [];
        
        $middleware = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'middleware';
            return $handler->handle($request);
        });

        $handler1 = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler1';
            return $this->response;
        });

        $handler2 = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler2';
            $response = $this->createMock(ResponseInterface::class);
            return $response;
        });

        $pipeline = new Pipeline([$middleware], $handler1);
        $newPipeline = $pipeline->withFallbackHandler($handler2);

        $pipeline->handle($this->request);
        $this->assertSame(['middleware', 'handler1'], $executionOrder);

        $executionOrder = [];
        $newPipeline->handle($this->request);
        $this->assertSame(['middleware', 'handler2'], $executionOrder);
    }

    #[Test]
    public function itWorksAsMiddlewareInAnotherPipeline(): void
    {
        $executionOrder = [];

        $middleware1 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'outer-1';
            return $handler->handle($request);
        });

        $middleware2 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'inner-1';
            return $handler->handle($request);
        });

        $middleware3 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'inner-2';
            return $handler->handle($request);
        });

        $middleware4 = $this->createMiddleware(function ($request, $handler) use (&$executionOrder) {
            $executionOrder[] = 'outer-2';
            return $handler->handle($request);
        });

        $handler = $this->createHandler(function () use (&$executionOrder) {
            $executionOrder[] = 'handler';
            return $this->response;
        });

        $innerPipeline = new Pipeline([$middleware2, $middleware3]);
        $outerPipeline = new Pipeline([$middleware1, $innerPipeline, $middleware4], $handler);

        $outerPipeline->handle($this->request);

        $this->assertSame(['outer-1', 'inner-1', 'inner-2', 'outer-2', 'handler'], $executionOrder);
    }

    #[Test]
    public function itMaintainsImmutabilityWhenPiping(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $pipeline1 = new Pipeline([$middleware1]);
        $pipeline2 = $pipeline1->pipe($middleware2);
        $pipeline3 = $pipeline1->pipe($middleware2);

        $this->assertNotSame($pipeline1, $pipeline2);
        $this->assertNotSame($pipeline1, $pipeline3);
        $this->assertNotSame($pipeline2, $pipeline3);

        $this->assertSame(1, $pipeline1->count());
        $this->assertSame(2, $pipeline2->count());
        $this->assertSame(2, $pipeline3->count());
    }

    #[Test]
    public function itHandlesGeneratorAsIterable(): void
    {
        $generator = function () {
            yield $this->createMock(MiddlewareInterface::class);
            yield $this->createMock(MiddlewareInterface::class);
        };

        $pipeline = new Pipeline($generator());

        $this->assertSame(2, $pipeline->count());
    }

    #[Test]
    public function itProvidesAccessToFallbackHandler(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $pipeline = new Pipeline([], $handler);

        $this->assertSame($handler, $pipeline->fallbackHandler);
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
