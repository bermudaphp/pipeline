<?php
declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use PHPUnit\Framework\TestCase;
use Bermuda\Http\Middleware\Pipeline;
use Bermuda\Http\Middleware\PipelineInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class PipelineTest extends TestCase
{
    /**
     * Проверяет, что конструктор корректно принимает валидный middleware.
     */
    public function testConstructorWithValidMiddleware(): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $pipeline = new Pipeline([$middleware]);
        $this->assertTrue($pipeline->has($middleware));
        $this->assertCount(1, $pipeline);
    }

    /**
     * Проверяет, что конструктор выбрасывает исключение при передаче невалидного middleware.
     */
    public function testConstructorWithInvalidMiddleware(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Передаём строку вместо объекта MiddlewareInterface
        new Pipeline(['not a middleware']);
    }

    /**
     * Тест метода has() для проверки наличия конкретного middleware.
     */
    public function testHasMethod(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {}
        };
        $pipeline = new Pipeline([$middleware1]);
        $this->assertTrue($pipeline->has($middleware1));
        $this->assertFalse($pipeline->has($middleware2));
    }

    /**
     * Тест методов count() и getIterator().
     */
    public function testCountAndIterator(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $pipeline = new Pipeline([$middleware1, $middleware2]);
        $this->assertCount(2, $pipeline);

        $iterated = [];
        foreach ($pipeline as $m) {
            $iterated[] = $m;
        }
        $this->assertSame([$middleware1, $middleware2], $iterated);
    }

    /**
     * Тест глубокого клонирования: при клонировании пайплайна каждый middleware должен быть клонирован.
     */
    public function testCloneDeepClone(): void
    {
        // Создаём тестовый middleware, который устанавливает флаг при клонировании.
        $middleware = new class implements MiddlewareInterface {
            public bool $wasCloned = false;

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                // Просто делегируем обработку дальше
                return $handler->handle($request);
            }

            public function __clone() {
                $this->wasCloned = true;
            }
        };

        $pipeline = new Pipeline([$middleware]);
        $clonedPipeline = clone $pipeline;

        $original = iterator_to_array($pipeline);
        $cloned   = iterator_to_array($clonedPipeline);

        // Убеждаемся, что объект в клонированном пайплайне – не тот же самый экземпляр, что и в оригинальном.
        $this->assertNotSame($original[0], $cloned[0]);
        $this->assertTrue($cloned[0]->wasCloned);
    }

    /**
     * Тест метода pipe() на иммутабельность: новый пайплайн содержит добавленное middleware,
     * а исходный остаётся без изменений.
     */
    public function testPipeImmutableAndCount(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $pipeline = new Pipeline([$middleware1]);

        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $newPipeline = $pipeline->pipe($middleware2);

        // Исходный пайплайн остаётся с одним middleware.
        $this->assertCount(1, $pipeline);
        // Новый пайплайн содержит два middleware.
        $this->assertCount(2, $newPipeline);
        $this->assertTrue($newPipeline->has($middleware1));
        $this->assertTrue($newPipeline->has($middleware2));
    }

    /**
     * Тест метода pipe() при передаче итерируемой коллекции middleware.
     */
    public function testPipeWithIterable(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $pipeline = new Pipeline([$middleware1]);

        // Передаём массив с middleware
        $newPipeline = $pipeline->pipe([$middleware2]);
        $this->assertCount(2, $newPipeline);
    }

    /**
     * Тест выбрасывания InvalidArgumentException, если передать невалидное значение в pipe().
     */
    public function testPipeThrowsInvalidArgumentException(): void
    {
        $pipeline = new Pipeline();
        $this->expectException(\InvalidArgumentException::class);
        // Передаём число вместо MiddlewareInterface
        $pipeline->pipe([123]);
    }

    /**
     * Тест выбрасывания RuntimeException, если попытаться добавить сам пайплайн в качестве middleware.
     */
    public function testPipeThrowsRuntimeExceptionWhenAddingPipelineItself(): void
    {
        $pipeline = new Pipeline();
        $this->expectException(\RuntimeException::class);
        $pipeline->pipe($pipeline);
    }

    /**
     * Тест, что цепочка middleware корректно делегирует обработку к fallback-обработчику.
     */
    public function testProcessWithMiddlewareDelegation(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $dummyResponse = $this->createMock(ResponseInterface::class);

        // Fallback-обработчик должен вернуть $dummyResponse.
        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($dummyResponse);

        // Middleware, которое просто делегирует обработку дальше.
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                return $handler->handle($request);
            }
        };

        $pipeline = new Pipeline([$middleware], $fallback);
        $response = $pipeline->handle($request);
        $this->assertSame($dummyResponse, $response);
    }

    /**
     * Тест, когда middleware возвращает собственный ответ и не делегирует обработку.
     */
    public function testProcessMiddlewareThatStopsChain(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $dummyResponse = $this->createMock(ResponseInterface::class);

        // Middleware, которое возвращает заранее подготовленный ответ.
        $middleware = new class($dummyResponse) implements MiddlewareInterface {
            private ResponseInterface $response;
            public function __construct(ResponseInterface $response) {
                $this->response = $response;
            }
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                return $this->response;
            }
        };

        // Fallback-обработчик НЕ должен вызываться.
        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->never())->method('handle');

        $pipeline = new Pipeline([$middleware], $fallback);
        $response = $pipeline->handle($request);
        $this->assertSame($dummyResponse, $response);
    }

    /**
     * Тест, демонстрирующий, что при исчерпании цепочки middleware вызывается fallback‑обработчик,
     * а указатель позиции сбрасывается для последующих вызовов.
     */
    public function testHandleResetsPositionAfterExhaustingMiddleware(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $dummyResponse = $this->createMock(ResponseInterface::class);

        // Middleware делегирует обработку.
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                return $handler->handle($request);
            }
        };

        // Fallback-обработчик возвращает $dummyResponse.
        $fallback = $this->createMock(RequestHandlerInterface::class);
        // Первый вызов fallback ожидается в процессе цепочки.
        $fallback->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($dummyResponse);

        $pipeline = new Pipeline([$middleware], $fallback);
        // Первый вызов: middleware делегирует, цепочка исчерпывается – fallback вызывается.
        $response1 = $pipeline->handle($request);
        $this->assertSame($dummyResponse, $response1);

        // При последующем вызове, поскольку положение сброшено, цепочка начнётся сначала.
        // Здесь создаём новый expectation для fallback.
        $fallback = $this->createMock(RequestHandlerInterface::class);
        $fallback->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($dummyResponse);
        // Меняем fallback через withFallbackHandler, чтобы протестировать эту функциональность.
        $newPipeline = $pipeline->withFallbackHandler($fallback);
        $response2 = $newPipeline->handle($request);
        $this->assertSame($dummyResponse, $response2);
    }

    /**
     * Тест статического конструктора createFromIterable().
     */
    public function testCreateFromIterable(): void
    {
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $fallback = $this->createMock(RequestHandlerInterface::class);

        $pipeline = Pipeline::createFromIterable([$middleware1, $middleware2], $fallback);
        $this->assertInstanceOf(PipelineInterface::class, $pipeline);
        $this->assertCount(2, $pipeline);
    }

    /**
     * Тест метода withFallbackHandler(): создаётся новый пайплайн с изменённым fallback‑обработчиком.
     */
    public function testWithFallbackHandler(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $dummyResponse1 = $this->createMock(ResponseInterface::class);
        $dummyResponse2 = $this->createMock(ResponseInterface::class);

        // Fallback-обработчик 1 возвращает dummyResponse1.
        $fallback1 = $this->createMock(RequestHandlerInterface::class);
        $fallback1->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($dummyResponse1);

        // Fallback-обработчик 2 возвращает dummyResponse2.
        $fallback2 = $this->createMock(RequestHandlerInterface::class);
        $fallback2->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($request))
            ->willReturn($dummyResponse2);

        // Middleware, которое всегда делегирует обработку.
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
                return $handler->handle($request);
            }
        };

        $pipeline = new Pipeline([$middleware], $fallback1);
        // Первый вызов использует fallback1.
        $response1 = $pipeline->handle($request);
        $this->assertSame($dummyResponse1, $response1);

        // С заменой fallback‑обработчика через withFallbackHandler().
        $newPipeline = $pipeline->withFallbackHandler($fallback2);
        $response2 = $newPipeline->handle($request);
        $this->assertSame($dummyResponse2, $response2);
    }
}
