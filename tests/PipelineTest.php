<?php

declare(strict_types=1);

namespace Bermuda\Pipeline\Tests;

use Bermuda\Pipeline\Pipeline;
use Bermuda\Pipeline\PipelineInterface;
use Bermuda\Pipeline\EmptyPipelineHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use InvalidArgumentException;
use RuntimeException;

class PipelineTest extends TestCase
{
    private ServerRequestInterface|MockObject $request;
    private ResponseInterface|MockObject $response;
    private RequestHandlerInterface|MockObject $handler;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
    }

    #[Test]
    public function emptyPipelineConstruction(): void
    {
        $pipeline = new Pipeline();

        $this->assertTrue($pipeline->isEmpty());
        $this->assertCount(0, $pipeline);
        $this->assertInstanceOf(EmptyPipelineHandler::class, $pipeline->fallbackHandler);
    }

    #[Test]
    public function pipelineWithMiddlewaresConstruction(): void
    {
        $middleware1 = $this->createTestMiddleware('middleware1');
        $middleware2 = $this->createTestMiddleware('middleware2');

        $pipeline = new Pipeline([$middleware1, $middleware2]);

        $this->assertFalse($pipeline->isEmpty());
        $this->assertEquals(2, $pipeline->count());
        $this->assertTrue($pipeline->has($middleware1));
        $this->assertTrue($pipeline->has($middleware2));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function pipelineWithCustomFallbackHandler(): void
    {
        $customHandler = $this->createMock(RequestHandlerInterface::class);
        $pipeline = new Pipeline([], $customHandler);

        $this->assertSame($customHandler, $pipeline->fallbackHandler);
    }

    #[Test]
    public function constructionWithInvalidMiddleware(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provided middlewares (0) does not implement');

        new Pipeline(['not a middleware']);
    }

    #[Test]
    public function constructionWithMixedValidAndInvalidMiddleware(): void
    {
        $middleware = $this->createTestMiddleware('valid');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provided middlewares (1) does not implement');

        new Pipeline([$middleware, 'invalid middleware']);
    }

    #[Test]
    public function hasWithMiddlewareInstance(): void
    {
        $middleware = $this->createTestMiddleware('test');
        $pipeline = new Pipeline([$middleware]);

        $this->assertTrue($pipeline->has($middleware));

        $otherMiddleware = $this->createTestMiddleware('other');
        $this->assertFalse($pipeline->has($otherMiddleware));

        $differentMiddleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $this->assertFalse($pipeline->has($differentMiddleware));
    }

    #[Test]
    public function hasWithClassName(): void
    {
        $middleware = $this->createTestMiddleware('test');
        $pipeline = new Pipeline([$middleware]);

        $this->assertTrue($pipeline->has($middleware::class));
        $this->assertFalse($pipeline->has('NonExistentClass'));
        $this->assertFalse($pipeline->has(self::class)); // Тест класс != Middleware
    }

    #[Test]
    public function testIsEmpty(): void
    {
        $pipeline = new Pipeline();
        $this->assertTrue($pipeline->isEmpty());

        $middleware = $this->createTestMiddleware('test');
        $pipelineWithMiddleware = new Pipeline([$middleware]);
        $this->assertFalse($pipelineWithMiddleware->isEmpty());
    }

    #[Test]
    public function testCount(): void
    {
        $pipeline = new Pipeline();
        $this->assertEquals(0, $pipeline->count());

        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');

        $pipelineWithMiddlewares = new Pipeline([$middleware1, $middleware2]);
        $this->assertCount(2, $pipelineWithMiddlewares);
    }

    #[Test]
    public function getIterator(): void
    {
        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');

        $pipeline = new Pipeline([$middleware1, $middleware2]);

        $middlewares = [];
        foreach ($pipeline as $middleware) {
            $middlewares[] = $middleware;
        }

        $this->assertCount(2, $middlewares);
        $this->assertSame($middleware1, $middlewares[0]);
        $this->assertSame($middleware2, $middlewares[1]);
    }

    #[Test]
    public function getIteratorWithEmptyPipeline(): void
    {
        $pipeline = new Pipeline();

        $middlewares = [];
        foreach ($pipeline as $middleware) {
            $middlewares[] = $middleware;
        }

        $this->assertEmpty($middlewares);
    }

    #[Test]
    /**
     * @throws Exception
     */
    public function testClone(): void
    {
        $middleware = $this->createTestMiddleware('test');
        $handler = $this->createMock(RequestHandlerInterface::class);

        $pipeline = new Pipeline([$middleware], $handler);
        $clonedPipeline = clone $pipeline;

        $this->assertNotSame($pipeline, $clonedPipeline);
        $this->assertEquals($pipeline->count(), $clonedPipeline->count());
        $this->assertNotSame($pipeline->fallbackHandler, $clonedPipeline->fallbackHandler);

        $originalMiddlewares = iterator_to_array($pipeline);
        $clonedMiddlewares = iterator_to_array($clonedPipeline);
        $this->assertNotSame($originalMiddlewares[0], $clonedMiddlewares[0]);
        $this->assertEquals($originalMiddlewares[0]::class, $clonedMiddlewares[0]::class);
    }

    #[Test]
    public function pipeWithSingleMiddleware(): void
    {
        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');

        $pipeline = new Pipeline([$middleware1]);
        $newPipeline = $pipeline->pipe($middleware2);

        $this->assertNotSame($pipeline, $newPipeline);
        $this->assertEquals(1, $pipeline->count());
        $this->assertEquals(2, $newPipeline->count());
        $this->assertTrue($newPipeline->has($middleware1));
        $this->assertTrue($newPipeline->has($middleware2));
    }

    #[Test]
    public function pipeWithIterableMiddlewares(): void
    {
        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');
        $middleware3 = $this->createTestMiddleware('m3');

        $pipeline = new Pipeline([$middleware1]);
        $newPipeline = $pipeline->pipe([$middleware2, $middleware3]);

        $this->assertEquals(1, $pipeline->count());
        $this->assertEquals(3, $newPipeline->count());
        $this->assertTrue($newPipeline->has($middleware1));
        $this->assertTrue($newPipeline->has($middleware2));
        $this->assertTrue($newPipeline->has($middleware3));
    }

    #[Test]
    public function pipeWithPrepend(): void
    {
        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');

        $pipeline = new Pipeline([$middleware1]);
        $newPipeline = $pipeline->pipe($middleware2, true);

        $middlewares = iterator_to_array($newPipeline);

        $this->assertEquals('m2', $middlewares[0]->getId());
        $this->assertEquals('m1', $middlewares[1]->getId());
        $this->assertCount(2, $middlewares);
    }

    #[Test]
    public function pipeWithMultiplePrependMiddlewares(): void
    {
        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');
        $middleware3 = $this->createTestMiddleware('m3');

        $pipeline = new Pipeline([$middleware1]);
        $newPipeline = $pipeline->pipe([$middleware2, $middleware3], true);

        $middlewares = iterator_to_array($newPipeline);

        $this->assertEquals('m3', $middlewares[0]->getId());
        $this->assertEquals('m2', $middlewares[1]->getId());
        $this->assertEquals('m1', $middlewares[2]->getId());
        $this->assertCount(3, $middlewares);
    }

    #[Test]
    public function pipeWithInvalidMiddleware(): void
    {
        $pipeline = new Pipeline();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provided middlewares (0) does not implement');

        $pipeline->pipe(['not a middleware']);
    }

    #[Test]
    public function pipeWithSelfReference(): void
    {
        $pipeline = new Pipeline();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Middleware cannot be the pipeline itself');

        $pipeline->pipe($pipeline);
    }

    #[Test]
    public function pipeWithSelfReferenceInArray(): void
    {
        $middleware = $this->createTestMiddleware('test');
        $pipeline = new Pipeline();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Middleware cannot be the pipeline itself');

        $pipeline->pipe([$middleware, $pipeline]);
    }

    #[Test]
    /**
     * @throws Exception
     */
    public function processWithMiddlewares(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $middleware1->expects($this->once())
            ->method('process')
            ->with($this->request, $this->isInstanceOf(Pipeline::class))
            ->willReturnCallback(function($request, $handler) {
                return $handler->process($request, $this->handler);
            });

        $middleware2->expects($this->once())
            ->method('process')
            ->with($this->request, $this->isInstanceOf(Pipeline::class))
            ->willReturnCallback(function($request, $handler) {
                return $handler->process($request, $this->handler);
            });

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $pipeline = new Pipeline([$middleware1, $middleware2]);
        $result = $pipeline->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    #[Test]
    public function processWithEmptyPipeline(): void
    {
        $pipeline = new Pipeline();

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result = $pipeline->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function processWithSingleMiddleware(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);

        $middleware->expects($this->once())
            ->method('process')
            ->with($this->request, $this->isInstanceOf(Pipeline::class))
            ->willReturn($this->response);

        $pipeline = new Pipeline([$middleware]);
        $result = $pipeline->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function handle(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $fallbackHandler = $this->createMock(RequestHandlerInterface::class);

        $middleware->expects($this->once())
            ->method('process')
            ->with($this->request, $this->isInstanceOf(Pipeline::class))
            ->willReturnCallback(function($request, $handler) {
                return $handler->process($request, $handler->fallbackHandler);
            });

        $fallbackHandler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $pipeline = new Pipeline([$middleware], $fallbackHandler);
        $result = $pipeline->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    #[Test]
    public function handleWithEmptyPipelineThrowsException(): void
    {
        $pipeline = new Pipeline();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to process the request. The pipeline is empty!');

        $pipeline->handle($this->request);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function handleWithCustomFallbackHandler(): void
    {
        $fallbackHandler = $this->createMock(RequestHandlerInterface::class);
        $fallbackHandler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $pipeline = new Pipeline([], $fallbackHandler);
        $result = $pipeline->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function createFromIterable(): void
    {
        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');
        $handler = $this->createMock(RequestHandlerInterface::class);

        $pipeline = Pipeline::createFromIterable([$middleware1, $middleware2], $handler);

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertEquals(2, $pipeline->count());
        $this->assertTrue($pipeline->has($middleware1));
        $this->assertTrue($pipeline->has($middleware2));
        $this->assertSame($handler, $pipeline->fallbackHandler);
    }

    #[Test]
    public function createFromIterableWithoutFallbackHandler(): void
    {
        $middleware = $this->createTestMiddleware('test');

        $pipeline = Pipeline::createFromIterable([$middleware]);

        $this->assertInstanceOf(EmptyPipelineHandler::class, $pipeline->fallbackHandler);
    }

    #[Test]
    public function createFromIterableWithEmptyIterable(): void
    {
        $pipeline = Pipeline::createFromIterable([]);

        $this->assertTrue($pipeline->isEmpty());
        $this->assertEquals(0, $pipeline->count());
        $this->assertInstanceOf(EmptyPipelineHandler::class, $pipeline->fallbackHandler);
    }

    #[Test]
    public function createFromIterableWithGenerator(): void
    {
        $generateMiddlewares = function() {
            yield $this->createTestMiddleware('m1');
            yield $this->createTestMiddleware('m2');
        };

        $pipeline = Pipeline::createFromIterable($generateMiddlewares());

        $this->assertEquals(2, $pipeline->count());
        $this->assertFalse($pipeline->isEmpty());
    }

    #[Test]
    public function withFallbackHandler(): void
    {
        $originalHandler = $this->createMock(RequestHandlerInterface::class);
        $newHandler = $this->createMock(RequestHandlerInterface::class);

        $pipeline = new Pipeline([], $originalHandler);
        $newPipeline = $pipeline->withFallbackHandler($newHandler);

        $this->assertNotSame($pipeline, $newPipeline);
        $this->assertSame($originalHandler, $pipeline->fallbackHandler);
        $this->assertSame($newHandler, $newPipeline->fallbackHandler);
    }

    #[Test]
    public function withFallbackHandlerKeepsMiddlewares(): void
    {
        $middleware = $this->createTestMiddleware('test');
        $originalHandler = $this->createMock(RequestHandlerInterface::class);
        $newHandler = $this->createMock(RequestHandlerInterface::class);

        $pipeline = new Pipeline([$middleware], $originalHandler);
        $newPipeline = $pipeline->withFallbackHandler($newHandler);

        $this->assertEquals($pipeline->count(), $newPipeline->count());
        $this->assertTrue($newPipeline->has($middleware));
    }

    #[Test]
    public function middlewareExecutionOrder(): void
    {
        $executionOrder = [];

        $middleware1 = new class($executionOrder) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'middleware1_before';
                $response = $handler->handle($request);
                $this->order[] = 'middleware1_after';
                return $response;
            }
        };

        $middleware2 = new class($executionOrder) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->order[] = 'middleware2_before';
                $response = $handler->handle($request);
                $this->order[] = 'middleware2_after';
                return $response;
            }
        };

        $handler = new class($executionOrder, $this->response) implements RequestHandlerInterface {
            public function __construct(private array &$order, private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->order[] = 'handler';
                return $this->response;
            }
        };

        $pipeline = new Pipeline([$middleware1, $middleware2], $handler);
        $pipeline->handle($this->request);

        $expectedOrder = [
            'middleware1_before',
            'middleware2_before',
            'handler',
            'middleware2_after',
            'middleware1_after'
        ];

        $this->assertEquals($expectedOrder, $executionOrder);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function positionResetAfterProcessing(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $pipeline = new Pipeline([$middleware]);

        $middleware->expects($this->exactly(2))
            ->method('process')
            ->with($this->request, $this->isInstanceOf(Pipeline::class))
            ->willReturnCallback(function($request, $handler) {
                return $handler->process($request, $this->handler);
            });

        $this->handler->expects($this->exactly(2))
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $result1 = $pipeline->process($this->request, $this->handler);
        $this->assertSame($this->response, $result1);

        $result2 = $pipeline->process($this->request, $this->handler);
        $this->assertSame($this->response, $result2);
    }

    #[Test]
    public function multipleIterations(): void
    {
        $middleware1 = $this->createTestMiddleware('m1');
        $middleware2 = $this->createTestMiddleware('m2');

        $pipeline = new Pipeline([$middleware1, $middleware2]);

        $firstIteration = [];
        foreach ($pipeline as $middleware) {
            $firstIteration[] = $middleware;
        }

        $secondIteration = [];
        foreach ($pipeline as $middleware) {
            $secondIteration[] = $middleware;
        }

        $this->assertEquals($firstIteration, $secondIteration);
        $this->assertCount(2, $firstIteration);
        $this->assertCount(2, $secondIteration);
        $this->assertEquals($firstIteration[0]->getId(), $secondIteration[0]->getId());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function processWithException(): void
    {
        $exception = new \RuntimeException('Middleware exception');
        $middleware = $this->createMock(MiddlewareInterface::class);

        $middleware->expects($this->once())
            ->method('process')
            ->willThrowException($exception);

        $pipeline = new Pipeline([$middleware]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Middleware exception');

        $pipeline->process($this->request, $this->handler);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function handleWithException(): void
    {
        $exception = new \RuntimeException('Handler exception');
        $fallbackHandler = $this->createMock(RequestHandlerInterface::class);

        $fallbackHandler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $pipeline = new Pipeline([], $fallbackHandler);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler exception');

        $pipeline->handle($this->request);
    }

    /**
     * Helper method to create test middleware instances with unique classes
     */
    private function createTestMiddleware(string $id): MiddlewareInterface
    {
        return match ($id) {
            'test' => new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }

                public function getId(): string
                {
                    return 'test';
                }
            },
            'other' => new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }

                public function getId(): string
                {
                    return 'other';
                }
            },
            'm1' => new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }

                public function getId(): string
                {
                    return 'm1';
                }
            },
            'm2' => new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }

                public function getId(): string
                {
                    return 'm2';
                }
            },
            'm3' => new class implements MiddlewareInterface {
                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }

                public function getId(): string
                {
                    return 'm3';
                }
            },
            default => new class($id) implements MiddlewareInterface {
                public function __construct(private string $id) {}

                public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                {
                    return $handler->handle($request);
                }

                public function getId(): string
                {
                    return $this->id;
                }
            }
        };
    }
}
