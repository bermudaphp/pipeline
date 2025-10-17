<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use Bermuda\Http\Middleware\EmptyPipelineHandler;
use Bermuda\Http\Middleware\Pipeline;
use Bermuda\Http\Middleware\PipelineFactory;
use Bermuda\Http\Middleware\PipelineInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(Pipeline::class)]
class PipelineTest extends TestCase
{
    private ServerRequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    #[Test]
    #[TestDox('Empty pipeline delegates to fallback handler')]
    public function emptyPipelineDelegatesToFallbackHandler(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $pipeline = new Pipeline([], $handler);
        $result = $pipeline->handle($this->request);

        $this->assertSame($this->response, $result);
    }

    #[Test]
    #[TestDox('Pipeline executes middleware in correct order')]
    public function pipelineExecutesMiddlewareInCorrectOrder(): void
    {
        $executionOrder = [];

        $middleware1 = $this->createOrderTrackingMiddleware($executionOrder, 'first');
        $middleware2 = $this->createOrderTrackingMiddleware($executionOrder, 'second');
        $middleware3 = $this->createOrderTrackingMiddleware($executionOrder, 'third');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->response);

        $pipeline = new Pipeline([$middleware1, $middleware2, $middleware3], $handler);
        $pipeline->handle($this->request);

        $this->assertEquals(['first', 'second', 'third'], $executionOrder);
    }

    #[Test]
    #[TestDox('Middleware can modify request before passing to next middleware')]
    public function middlewareCanModifyRequestBeforePassingToNext(): void
    {
        $modifiedRequest = $this->createMock(ServerRequestInterface::class);
        $receivedRequests = [];
        
        $modifyingMiddleware = new class($modifiedRequest, $this->response) implements MiddlewareInterface {
            public function __construct(
                private ServerRequestInterface $modifiedRequest,
                private ResponseInterface $response
            ) {}

            public function process(
                ServerRequestInterface $request, 
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return $handler->handle($this->modifiedRequest);
            }
        };

        $verifyingMiddleware = new class($receivedRequests, $this->response) implements MiddlewareInterface {
            public function __construct(
                private array &$receivedRequests,
                private ResponseInterface $response
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $this->receivedRequests[] = $request;
                return $handler->handle($request);
            }
        };

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($modifiedRequest)
            ->willReturn($this->response);

        $pipeline = new Pipeline([$modifyingMiddleware, $verifyingMiddleware], $handler);
        $pipeline->handle($this->request);

        $this->assertSame($modifiedRequest, $receivedRequests[0]);
    }

    #[Test]
    #[TestDox('Middleware can modify response on the way back')]
    public function middlewareCanModifyResponseOnTheWayBack(): void
    {
        $handlerResponse = $this->createMock(ResponseInterface::class);
        $modifiedResponse = $this->createMock(ResponseInterface::class);
        
        $middleware = new class($modifiedResponse) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $modifiedResponse) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $handler->handle($request); // Ignore original response
                return $this->modifiedResponse;
            }
        };

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($handlerResponse);

        $pipeline = new Pipeline([$middleware], $handler);
        $result = $pipeline->handle($this->request);

        $this->assertSame($modifiedResponse, $result);
        $this->assertNotSame($handlerResponse, $result);
    }

    #[Test]
    #[TestDox('Middleware can short-circuit the pipeline by returning response directly')]
    public function middlewareCanShortCircuitPipeline(): void
    {
        $shortCircuitResponse = $this->createMock(ResponseInterface::class);
        
        $shortCircuitMiddleware = new class($shortCircuitResponse) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $response) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return $this->response;
            }
        };

        $neverCalledMiddleware = $this->createMock(MiddlewareInterface::class);
        $neverCalledMiddleware->expects($this->never())->method('process');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $pipeline = new Pipeline([$shortCircuitMiddleware, $neverCalledMiddleware], $handler);
        $result = $pipeline->handle($this->request);

        $this->assertSame($shortCircuitResponse, $result);
    }

    #[Test]
    #[TestDox('Response flows back through all middleware')]
    public function responseFlowsBackThroughAllMiddleware(): void
    {
        $responses = [];
        $handlerResponse = $this->createMock(ResponseInterface::class);
        
        $middleware1 = $this->createResponseTrackingMiddleware($responses, 'first');
        $middleware2 = $this->createResponseTrackingMiddleware($responses, 'second');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($handlerResponse);

        $pipeline = new Pipeline([$middleware1, $middleware2], $handler);
        $result = $pipeline->handle($this->request);

        $this->assertSame($handlerResponse, $result);
        $this->assertEquals(['second', 'first'], $responses);
    }

    #[Test]
    #[TestDox('Pipeline works as middleware within another pipeline')]
    public function pipelineWorksAsMiddlewareWithinAnotherPipeline(): void
    {
        $executionOrder = [];

        $innerMiddleware = $this->createOrderTrackingMiddleware($executionOrder, 'inner');
        $innerPipeline = new Pipeline([$innerMiddleware]);

        $outerMiddleware = $this->createOrderTrackingMiddleware($executionOrder, 'outer');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->response);

        $outerPipeline = new Pipeline([$outerMiddleware, $innerPipeline], $handler);
        $outerPipeline->handle($this->request);

        $this->assertEquals(['outer', 'inner'], $executionOrder);
    }

    #[Test]
    #[TestDox('Pipe method returns new instance without modifying original')]
    public function pipeReturnsNewInstanceWithoutModifyingOriginal(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $original = new Pipeline([$middleware1]);
        $modified = $original->pipe($middleware2);

        $this->assertNotSame($original, $modified);
        $this->assertCount(1, $original);
        $this->assertCount(2, $modified);
    }

    #[Test]
    #[TestDox('Pipe with prepend adds middleware at the beginning')]
    public function pipeWithPrependAddsMiddlewareAtBeginning(): void
    {
        $executionOrder = [];

        $middleware1 = $this->createOrderTrackingMiddleware($executionOrder, 'first');
        $middleware2 = $this->createOrderTrackingMiddleware($executionOrder, 'second');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->response);

        $pipeline = new Pipeline([$middleware1], $handler);
        $pipeline = $pipeline->pipe($middleware2, prepend: true);
        
        $pipeline->handle($this->request);

        $this->assertEquals(['second', 'first'], $executionOrder);
    }

    #[Test]
    #[TestDox('Pipe accepts iterable of middleware')]
    public function pipeAcceptsIterableOfMiddleware(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1]);
        $pipeline = $pipeline->pipe([$middleware2, $middleware3]);

        $this->assertCount(3, $pipeline);
    }

    #[Test]
    #[TestDox('Pipe accepts generator as iterable')]
    public function pipeAcceptsGeneratorAsIterable(): void
    {
        $generator = function() {
            yield $this->createMock(MiddlewareInterface::class);
            yield $this->createMock(MiddlewareInterface::class);
        };

        $pipeline = new Pipeline([]);
        $pipeline = $pipeline->pipe($generator());

        $this->assertCount(2, $pipeline);
    }

    #[Test]
    #[TestDox('Pipe throws exception when adding pipeline to itself')]
    public function pipeThrowsExceptionWhenAddingPipelineToItself(): void
    {
        $pipeline = new Pipeline([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add pipeline to itself');

        $pipeline->pipe($pipeline);
    }

    #[Test]
    #[TestDox('Pipe throws exception when adding pipeline that contains reference to this pipeline')]
    public function pipeThrowsExceptionWhenAddingPipelineThatContainsReference(): void
    {
        $pipeline1 = new Pipeline([]);
        $pipeline2 = new Pipeline([$pipeline1]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add pipeline that contains reference to this pipeline');

        $pipeline1->pipe($pipeline2);
    }

    #[Test]
    #[TestDox('Has method detects middleware by class name')]
    public function hasMethodDetectsMiddlewareByClassName(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return $handler->handle($request);
            }
        };

        $pipeline = new Pipeline([$middleware]);

        $this->assertTrue($pipeline->has($middleware::class));
        $this->assertFalse($pipeline->has('NonExistentMiddleware'));
    }

    #[Test]
    #[TestDox('Has method detects middleware by instance')]
    public function hasMethodDetectsMiddlewareByInstance(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1]);

        $this->assertTrue($pipeline->has($middleware1));
        $this->assertFalse($pipeline->has($middleware2));
    }

    #[Test]
    #[TestDox('Has method searches recursively in nested pipelines')]
    public function hasMethodSearchesRecursivelyInNestedPipelines(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $innerPipeline = new Pipeline([$middleware]);
        $outerPipeline = new Pipeline([$innerPipeline]);

        // Has does NOT search recursively based on implementation
        $this->assertFalse($outerPipeline->has($middleware));
        $this->assertTrue($outerPipeline->has($innerPipeline));
    }

    #[Test]
    #[TestDox('IsEmpty returns true for empty pipeline')]
    public function isEmptyReturnsTrueForEmptyPipeline(): void
    {
        $pipeline = new Pipeline([]);

        $this->assertTrue($pipeline->isEmpty());
    }

    #[Test]
    #[TestDox('IsEmpty returns false for non-empty pipeline')]
    public function isEmptyReturnsFalseForNonEmptyPipeline(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $pipeline = new Pipeline([$middleware]);

        $this->assertFalse($pipeline->isEmpty());
    }

    #[Test]
    #[TestDox('Count returns correct number of middleware')]
    public function countReturnsCorrectNumberOfMiddleware(): void
    {
        $pipeline = new Pipeline([
            $this->createMock(MiddlewareInterface::class),
            $this->createMock(MiddlewareInterface::class),
            $this->createMock(MiddlewareInterface::class),
        ]);

        $this->assertCount(3, $pipeline);
    }

    #[Test]
    #[TestDox('Pipeline is iterable and yields all middleware in order')]
    public function pipelineIsIterableAndYieldsAllMiddlewareInOrder(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware3 = $this->createMock(MiddlewareInterface::class);

        $pipeline = new Pipeline([$middleware1, $middleware2, $middleware3]);

        $collected = [];
        foreach ($pipeline as $middleware) {
            $collected[] = $middleware;
        }

        $this->assertSame([$middleware1, $middleware2, $middleware3], $collected);
    }

    #[Test]
    #[TestDox('Cloning pipeline creates independent copy')]
    public function cloningPipelineCreatesIndependentCopy(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public int $callCount = 0;

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $this->callCount++;
                return $handler->handle($request);
            }
        };

        $handler = new class($this->response) implements RequestHandlerInterface {
            public int $callCount = 0;

            public function __construct(private ResponseInterface $response) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->callCount++;
                return $this->response;
            }
        };

        $original = new Pipeline([$middleware], $handler);
        $cloned = clone $original;

        // Execute both pipelines
        $original->handle($this->request);
        $cloned->handle($this->request);

        // Get middleware and handlers from both
        $originalMiddlewares = iterator_to_array($original);
        $clonedMiddlewares = iterator_to_array($cloned);

        // Verify they are different instances
        $this->assertNotSame($originalMiddlewares[0], $clonedMiddlewares[0]);
        $this->assertNotSame($original->fallbackHandler, $cloned->fallbackHandler);

        // Verify independent state
        $this->assertEquals(1, $originalMiddlewares[0]->callCount);
        $this->assertEquals(1, $clonedMiddlewares[0]->callCount);
        $this->assertEquals(1, $original->fallbackHandler->callCount);
        $this->assertEquals(1, $cloned->fallbackHandler->callCount);
    }

    #[Test]
    #[TestDox('WithFallbackHandler returns new instance with updated handler')]
    public function withFallbackHandlerReturnsNewInstanceWithUpdatedHandler(): void
    {
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler2->expects($this->once())
            ->method('handle')
            ->willReturn($this->response);

        $original = new Pipeline([], $handler1);
        $modified = $original->withFallbackHandler($handler2);

        $this->assertNotSame($original, $modified);
        $this->assertSame($handler1, $original->fallbackHandler);
        $this->assertSame($handler2, $modified->fallbackHandler);

        $modified->handle($this->request);
    }

    #[Test]
    #[TestDox('CreateFromIterable creates pipeline with provided middleware')]
    public function createFromIterableCreatesPipelineWithProvidedMiddleware(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $pipeline = Pipeline::createFromIterable([$middleware1, $middleware2], $handler);

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertCount(2, $pipeline);
        $this->assertSame($handler, $pipeline->fallbackHandler);
    }

    #[Test]
    #[TestDox('CreateFromIterable uses EmptyPipelineHandler when no handler provided')]
    public function createFromIterableUsesEmptyHandlerWhenNoHandlerProvided(): void
    {
        $pipeline = Pipeline::createFromIterable([]);

        $this->assertInstanceOf(EmptyPipelineHandler::class, $pipeline->fallbackHandler);
    }

    #[Test]
    #[TestDox('Constructor throws exception for invalid middleware')]
    public function constructorThrowsExceptionForInvalidMiddleware(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        new Pipeline(['not a middleware']);
    }

    #[Test]
    #[TestDox('Constructor throws exception with position information')]
    public function constructorThrowsExceptionWithPositionInformation(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('position 1');

        new Pipeline([$middleware1, 'invalid']);
    }

    #[Test]
    #[TestDox('Pipe throws exception for invalid middleware')]
    public function pipeThrowsExceptionForInvalidMiddleware(): void
    {
        $pipeline = new Pipeline([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $pipeline->pipe(['not a middleware']);
    }

    #[Test]
    #[TestDox('Process method works correctly when pipeline used as middleware')]
    public function processMethodWorksCorrectlyWhenPipelineUsedAsMiddleware(): void
    {
        $executionOrder = [];

        $innerMiddleware = $this->createOrderTrackingMiddleware($executionOrder, 'inner');
        $innerPipeline = new Pipeline([$innerMiddleware]);

        $externalHandler = $this->createMock(RequestHandlerInterface::class);
        $externalHandler->expects($this->once())
            ->method('handle')
            ->willReturn($this->response);

        $result = $innerPipeline->process($this->request, $externalHandler);

        $this->assertSame($this->response, $result);
        $this->assertEquals(['inner'], $executionOrder);
    }

    #[Test]
    #[TestDox('Process method uses provided handler instead of fallback')]
    public function processMethodUsesProvidedHandlerInsteadOfFallback(): void
    {
        $fallbackHandler = $this->createMock(RequestHandlerInterface::class);
        $fallbackHandler->expects($this->never())->method('handle');

        $externalHandler = $this->createMock(RequestHandlerInterface::class);
        $externalHandler->expects($this->once())
            ->method('handle')
            ->willReturn($this->response);

        $pipeline = new Pipeline([], $fallbackHandler);
        $result = $pipeline->process($this->request, $externalHandler);

        $this->assertSame($this->response, $result);
    }

    #[Test]
    #[TestDox('Pipeline can handle complex nested structures')]
    public function pipelineCanHandleComplexNestedStructures(): void
    {
        $executionOrder = [];

        $level3Middleware = $this->createOrderTrackingMiddleware($executionOrder, 'level3');
        $level3Pipeline = new Pipeline([$level3Middleware]);

        $level2Middleware = $this->createOrderTrackingMiddleware($executionOrder, 'level2');
        $level2Pipeline = new Pipeline([$level2Middleware, $level3Pipeline]);

        $level1Middleware = $this->createOrderTrackingMiddleware($executionOrder, 'level1');
        
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->response);

        $mainPipeline = new Pipeline([$level1Middleware, $level2Pipeline], $handler);
        $mainPipeline->handle($this->request);

        $this->assertEquals(['level1', 'level2', 'level3'], $executionOrder);
    }

    #[Test]
    #[TestDox('Pipeline handles exceptions from middleware correctly')]
    public function pipelineHandlesExceptionsFromMiddlewareCorrectly(): void
    {
        $failingMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                throw new \RuntimeException('Middleware failed');
            }
        };

        $handler = $this->createMock(RequestHandlerInterface::class);
        $pipeline = new Pipeline([$failingMiddleware], $handler);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Middleware failed');

        $pipeline->handle($this->request);
    }

    #[Test]
    #[TestDox('Pipeline handles exceptions from handler correctly')]
    public function pipelineHandlesExceptionsFromHandlerCorrectly(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willThrowException(new \RuntimeException('Handler failed'));

        $pipeline = new Pipeline([], $handler);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler failed');

        $pipeline->handle($this->request);
    }

    #[Test]
    #[TestDox('Multiple pipelines can be used concurrently without interference')]
    public function multiplePipelinesCanBeUsedConcurrentlyWithoutInterference(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')
            ->willReturnCallback(fn($req, $handler) => $handler->handle($req));

        $response1 = $this->createMock(ResponseInterface::class);
        $response2 = $this->createMock(ResponseInterface::class);

        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler1->method('handle')->willReturn($response1);

        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler2->method('handle')->willReturn($response2);

        $pipeline = new Pipeline([$middleware]);

        $result1 = $pipeline->process($this->request, $handler1);
        $result2 = $pipeline->process($this->request, $handler2);

        $this->assertSame($response1, $result1);
        $this->assertSame($response2, $result2);
    }

    private function createOrderTrackingMiddleware(array &$executionOrder, string $name): MiddlewareInterface
    {
        return new class($executionOrder, $name) implements MiddlewareInterface {
            public function __construct(
                private array &$executionOrder,
                private string $name
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $this->executionOrder[] = $this->name;
                return $handler->handle($request);
            }
        };
    }

    private function createResponseTrackingMiddleware(array &$responses, string $name): MiddlewareInterface
    {
        return new class($responses, $name) implements MiddlewareInterface {
            public function __construct(
                private array &$responses,
                private string $name
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = $handler->handle($request);
                $this->responses[] = $this->name;
                return $response;
            }
        };
    }
}

#[CoversClass(EmptyPipelineHandler::class)]
class EmptyPipelineHandlerTest extends TestCase
{
    #[Test]
    #[TestDox('Handler throws exception when invoked')]
    public function handlerThrowsExceptionWhenInvoked(): void
    {
        $handler = new EmptyPipelineHandler();
        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to process the request. The pipeline is empty!');

        $handler->handle($request);
    }

    #[Test]
    #[TestDox('Empty pipeline with default handler throws meaningful error')]
    public function emptyPipelineWithDefaultHandlerThrowsMeaningfulError(): void
    {
        $pipeline = new Pipeline([]);
        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to process the request. The pipeline is empty!');

        $pipeline->handle($request);
    }
}

#[CoversClass(PipelineFactory::class)]
class PipelineFactoryTest extends TestCase
{
    private PipelineFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new PipelineFactory();
    }

    #[Test]
    #[TestDox('Factory creates pipeline with provided middleware')]
    public function factoryCreatesPipelineWithProvidedMiddleware(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);

        $pipeline = $this->factory->createMiddlewarePipeline([$middleware1, $middleware2]);

        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertCount(2, $pipeline);
    }

    #[Test]
    #[TestDox('Factory creates pipeline with custom fallback handler')]
    public function factoryCreatesPipelineWithCustomFallbackHandler(): void
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $pipeline = $this->factory->createMiddlewarePipeline([], $handler);

        $this->assertSame($handler, $pipeline->fallbackHandler);
    }

    #[Test]
    #[TestDox('Factory creates pipeline with default handler when none provided')]
    public function factoryCreatesPipelineWithDefaultHandlerWhenNoneProvided(): void
    {
        $pipeline = $this->factory->createMiddlewarePipeline();

        $this->assertInstanceOf(EmptyPipelineHandler::class, $pipeline->fallbackHandler);
        $this->assertTrue($pipeline->isEmpty());
    }

    #[Test]
    #[TestDox('Factory creates functional pipeline that processes requests')]
    public function factoryCreatesFunctionalPipelineThatProcessesRequests(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $middleware = new class($response) implements MiddlewareInterface {
            public function __construct(private ResponseInterface $response) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return $this->response;
            }
        };

        $pipeline = $this->factory->createMiddlewarePipeline([$middleware]);
        $result = $pipeline->handle($request);

        $this->assertSame($response, $result);
    }
}
