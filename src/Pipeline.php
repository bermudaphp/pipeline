
<?php

namespace Bermuda\Http\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Pipeline
 *
 * Stateless middleware pipeline that processes HTTP requests through a chain of PSR-15 middleware.
 * Uses SplQueue for efficient sequential processing without position tracking.
 *
 * Key features:
 * - Thread-safe: No mutable position state
 * - Immutable: pipe() returns new instances
 * - Composable: Pipelines can contain other pipelines
 * - PSR-15 compliant: Implements MiddlewareInterface and RequestHandlerInterface
 *
 * @example Basic usage
 * ```php
 * $pipeline = new Pipeline([
 *     new AuthenticationMiddleware(),
 *     new LoggingMiddleware(),
 *     new CorsMiddleware(),
 * ], new ApplicationHandler());
 *
 * $response = $pipeline->handle($request);
 * ```
 */
final class Pipeline implements PipelineInterface
{
    /**
     * Queue of middleware objects that implement MiddlewareInterface.
     *
     * @var \SplQueue<MiddlewareInterface>
     */
    private \SplQueue $middlewares;

    /**
     * Constructor for the Pipeline class.
     *
     * @param iterable<MiddlewareInterface> $middlewares An iterable collection of middleware objects.
     * @param RequestHandlerInterface $fallbackHandler The fallback handler to use when the middleware chain is exhausted.
     *
     * @throws \InvalidArgumentException if any middleware does not implement MiddlewareInterface.
     */
    public function __construct(
        iterable $middlewares = [],
        private(set) RequestHandlerInterface $fallbackHandler = new EmptyPipelineHandler
    ) {
        $this->middlewares = new \SplQueue();
        
        foreach ($middlewares as $i => $middleware) {
            $this->validateMiddleware($middleware, $i);
            $this->middlewares->enqueue($middleware);
        }
    }

    /**
     * @template T of MiddlewareInterface
     * Checks if a specific middleware exists in the pipeline.
     *
     * @param MiddlewareInterface|class-string<T> $middleware The middleware instance or class to search for.
     * @return bool Returns true if the middleware exists; false otherwise.
     */
    public function has(MiddlewareInterface|string $middleware): bool
    {
        foreach ($this->middlewares as $m) {
            // Check by instance (identity)
            if ($middleware instanceof MiddlewareInterface && $m === $middleware) {
                return true;
            }
            
            // Check by class
            if (is_string($middleware) && $m::class === $middleware) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the pipeline is empty.
     *
     * @return bool True if there are no middleware registered; false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->middlewares->isEmpty();
    }

    /**
     * Returns the number of middleware objects in the pipeline.
     *
     * @return int The count of middleware components.
     */
    public function count(): int
    {
        return $this->middlewares->count();
    }

    /**
     * Retrieves an external iterator over the middleware collection.
     *
     * @return \Generator<MiddlewareInterface> A generator that yields each middleware in the pipeline.
     */
    public function getIterator(): \Generator
    {
        foreach ($this->middlewares as $middleware) {
            yield $middleware;
        }
    }

    /**
     * Performs a clone of the pipeline.
     *
     * Clones the queue structure and the fallback handler.
     * Middleware themselves are not cloned as they should be stateless.
     */
    public function __clone(): void
    {
        $this->middlewares = clone $this->middlewares;
        $this->fallbackHandler = clone $this->fallbackHandler;
    }

    /**
     * Adds additional middleware to the pipeline.
     *
     * @param iterable|MiddlewareInterface $middlewares One or more middleware to add.
     * @param bool $prepend If true, adds the middleware at the beginning of the chain.
     *
     * @return PipelineInterface A new Pipeline instance with the added middleware.
     *
     * @throws \InvalidArgumentException If any provided middleware does not implement MiddlewareInterface.
     * @throws \RuntimeException If middleware refers to the pipeline itself.
     */
    public function pipe(iterable|MiddlewareInterface $middlewares, bool $prepend = false): PipelineInterface
    {
        $copy = clone $this;

        if ($middlewares instanceof MiddlewareInterface) {
            $middlewares = [$middlewares];
        }

        foreach ($middlewares as $i => $middleware) {
            $this->isMiddlewareInstance($middleware, $i);

            if ($middleware === $this) {
                throw new \RuntimeException('Cannot add pipeline to itself - this would create circular reference');
            }

            if ($middleware instanceof PipelineInterface && $middleware->has($this)) {
                throw new \RuntimeException('Cannot add pipeline that contains reference to this pipeline');
            }

            $prepend ? $copy->middlewares->unshift($middleware) :
                $copy->middlewares->enqueue($middleware);
        }

        return $copy;
    }

    /**
     * Processes a request through the middleware chain.
     *
     * @param ServerRequestInterface $request The server request to process.
     * @param RequestHandlerInterface $handler The handler to use when middleware chain is exhausted.
     * @return ResponseInterface The HTTP response.
     *
     * @throws \RuntimeException If handler is the pipeline itself (would cause double execution).
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($handler === $this) {
            throw new \RuntimeException('Cannot use pipeline as its own handler - this would cause all middleware to execute twice');
        }
        
        $next = new Next($this->middlewares, $handler);
        return $next->handle($request);
    }

    /**
     * Handles a request through the middleware chain using the fallback handler.
     *
     * @param ServerRequestInterface $request The server request to handle.
     * @return ResponseInterface The HTTP response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface 
    {
        $next = new Next($this->middlewares, $this->fallbackHandler);
        return $next->handle($request);
    }

    /**
     * Validates that the given value is middleware.
     *
     * @param mixed $middleware The value to validate.
     * @param int|string $position The position of the middleware in the collection.
     * @return void
     *
     * @throws \InvalidArgumentException If the value does not implement MiddlewareInterface.
     */
    private function isMiddlewareInstance(mixed $middleware, int|string $position): void
    {
        if (!$middleware instanceof MiddlewareInterface) {
            $type = get_debug_type($middleware);
            throw new \InvalidArgumentException(
                "Middleware at position $position must implement " . MiddlewareInterface::class . ", $type given"
            );
        }
    }

    /**
     * Creates a new Pipeline instance from a set of middleware provided as an iterable.
     *
     * @param iterable<MiddlewareInterface> $middlewares An iterable collection of middleware objects.
     * @param ?RequestHandlerInterface $fallbackHandler The fallback handler to use.
     *
     * @return self Returns a fully configured Pipeline instance.
     */
    public static function createFromIterable(iterable $middlewares, ?RequestHandlerInterface $fallbackHandler = null): PipelineInterface
    {
        return new self($middlewares, $fallbackHandler ?? new EmptyPipelineHandler);
    }

    /**
     * Returns a new Pipeline instance with the updated fallback handler.
     *
     * @param RequestHandlerInterface $handler The new fallback handler to be used when the middleware chain is exhausted.
     *
     * @return PipelineInterface Returns the cloned pipeline instance with the updated fallback handler.
     */
    public function withFallbackHandler(RequestHandlerInterface $handler): PipelineInterface
    {
        $clone = clone $this;
        $clone->fallbackHandler = $handler;

        return $clone;
    }
}
