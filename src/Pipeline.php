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
 * This implementation uses a recursive approach without mutable state, making it thread-safe
 * and safe for concurrent request processing.
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
 *
 * @example Pipeline composition
 * ```php
 * $apiPipeline = new Pipeline([
 *     new RateLimitMiddleware(),
 *     new JsonMiddleware(),
 * ]);
 *
 * $mainPipeline = new Pipeline([
 *     new LoggingMiddleware(),
 *     $apiPipeline, // Pipeline as middleware
 * ], $handler);
 * ```
 */
final class Pipeline implements PipelineInterface
{
    /**
     * An array of middleware objects that implement MiddlewareInterface.
     *
     * @var MiddlewareInterface[]
     */
    private array $middlewares = [];

    /**
     * Constructor for the Pipeline class.
     *
     * Initializes the Pipeline with an optional iterable of middleware and a fallback handler.
     * Each middleware in the provided iterable is verified to implement MiddlewareInterface.
     * If any middleware fails this verification, an InvalidArgumentException is thrown.
     *
     * @param iterable<MiddlewareInterface> $middlewares An iterable collection of middleware objects.
     * @param RequestHandlerInterface $fallbackHandler The fallback handler to use when the middleware chain is exhausted.
     *                                                  Defaults to an instance of EmptyPipelineHandler.
     *
     * @throws \InvalidArgumentException if any middleware does not implement MiddlewareInterface.
     *
     * @example
     * ```php
     * $pipeline = new Pipeline([
     *     new AuthMiddleware(),
     *     new ValidationMiddleware(),
     * ], new AppHandler());
     * ```
     */
    public function __construct(
        iterable $middlewares = [],
        private(set) RequestHandlerInterface $fallbackHandler = new EmptyPipelineHandler
    ) {
        foreach ($middlewares as $i => $middleware) {
            $this->validateMiddleware($middleware, $i);
            $this->middlewares[] = $middleware;
        }
    }

    /**
     * @template T of MiddlewareInterface
     * Checks if a specific middleware exists in the pipeline.
     *
     * @param MiddlewareInterface|class-string<T> $middleware The middleware instance or class to search for.
     * @return bool Returns true if the middleware exists; false otherwise.
     *
     * @example Check by class name
     * ```php
     * if ($pipeline->has(AuthMiddleware::class)) {
     *     // Pipeline includes authentication
     * }
     * ```
     *
     * @example Check by instance
     * ```php
     * $auth = new AuthMiddleware();
     * if ($pipeline->has($auth)) {
     *     // This specific instance is in the pipeline
     * }
     * ```
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
     *
     * @example
     * ```php
     * if ($pipeline->isEmpty()) {
     *     // Request will go directly to fallback handler
     * }
     * ```
     */
    public function isEmpty(): bool
    {
        return empty($this->middlewares);
    }

    /**
     * Returns the number of middleware objects in the pipeline.
     *
     * Implements the Countable interface.
     *
     * @return int The count of middleware components.
     *
     * @example
     * ```php
     * echo "Pipeline has " . count($pipeline) . " middleware";
     * ```
     */
    public function count(): int
    {
        return count($this->middlewares);
    }

    /**
     * Retrieves an external iterator over the middleware collection.
     *
     * Implements the IteratorAggregate interface, allowing foreach iteration over the middleware.
     *
     * @return \Generator<MiddlewareInterface> A generator that yields each middleware in the pipeline.
     *
     * @example
     * ```php
     * foreach ($pipeline as $middleware) {
     *     echo get_class($middleware) . "\n";
     * }
     * ```
     */
    public function getIterator(): \Generator
    {
        yield from $this->middlewares;
    }

    /**
     * Performs a deep clone of the pipeline.
     *
     * When cloning, each registered middleware and the fallback handler are also cloned
     * to prevent shared state issues.
     */
    public function __clone()
    {
        foreach ($this->middlewares as $i => $middleware) {
            $this->middlewares[$i] = clone $middleware;
        }
        $this->fallbackHandler = clone $this->fallbackHandler;
    }

    /**
     * Adds additional middleware to the pipeline.
     *
     * This method appends (or prepends, based on the $prepend flag) the specified middleware to the pipeline.
     * It returns a new Pipeline instance with the middleware integrated, leaving the original instance unchanged.
     *
     * @param iterable|MiddlewareInterface $middlewares One or more middleware to add.
     * @param bool $prepend If true, adds the middleware at the beginning of the chain.
     *
     * @return PipelineInterface A new Pipeline instance with the added middleware.
     *
     * @throws \InvalidArgumentException If any provided middleware does not implement MiddlewareInterface.
     * @throws \RuntimeException If middleware refers to the pipeline itself.
     *
     * @example Append middleware
     * ```php
     * $pipeline = $pipeline->pipe([
     *     new CacheMiddleware(),
     *     new CompressionMiddleware(),
     * ]);
     * ```
     *
     * @example Prepend middleware (execute first)
     * ```php
     * $pipeline = $pipeline->pipe(new SecurityHeadersMiddleware(), prepend: true);
     * ```
     */
    public function pipe(iterable|MiddlewareInterface $middlewares, bool $prepend = false): PipelineInterface
    {
        $copy = clone $this;

        if ($middlewares instanceof MiddlewareInterface) {
            $middlewares = [$middlewares];
        }

        foreach ($middlewares as $i => $middleware) {
            $this->validateMiddleware($middleware, $i);

            if ($middleware === $this) {
                throw new \RuntimeException('Cannot add pipeline to itself - this would create circular reference');
            }

            // Check for nested circular references
            if ($middleware instanceof PipelineInterface && $middleware->has($this)) {
                throw new \RuntimeException('Cannot add pipeline that contains reference to this pipeline');
            }

            if ($prepend) {
                array_unshift($copy->middlewares, $middleware);
            } else {
                $copy->middlewares[] = $middleware;
            }
        }

        return $copy;
    }

    /**
     * Processes a request through the middleware chain.
     *
     * This method implements MiddlewareInterface::process(), allowing the pipeline
     * to act as middleware within another pipeline.
     *
     * @param ServerRequestInterface $request The server request to process.
     * @param RequestHandlerInterface $handler The handler to use when middleware chain is exhausted.
     * @return ResponseInterface The HTTP response.
     *
     * @example Using pipeline as middleware
     * ```php
     * $innerPipeline = new Pipeline([new ValidatorMiddleware()]);
     * 
     * $outerPipeline = new Pipeline([
     *     new LoggingMiddleware(),
     *     $innerPipeline, // Acts as middleware
     * ], $handler);
     * ```
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->processMiddleware($request, 0, $handler);
    }

    /**
     * Handles a request through the middleware chain using the fallback handler.
     *
     * This method implements RequestHandlerInterface::handle(), allowing the pipeline
     * to act as a request handler.
     *
     * @param ServerRequestInterface $request The server request to handle.
     * @return ResponseInterface The HTTP response.
     *
     * @example
     * ```php
     * $pipeline = new Pipeline($middlewares, $appHandler);
     * $response = $pipeline->handle($request);
     * ```
     */
    public function handle(ServerRequestInterface $request): ResponseInterface 
    {
        return $this->processMiddleware($request, 0, $this->fallbackHandler);
    }

    /**
     * Recursively processes middleware starting from the given position.
     *
     * This stateless approach eliminates the need for mutable position tracking,
     * making the pipeline safe for concurrent use and easier to reason about.
     *
     * @param ServerRequestInterface $request The request to process.
     * @param int $position Current position in the middleware chain.
     * @param RequestHandlerInterface $finalHandler Handler to use when all middleware are exhausted.
     * @return ResponseInterface The HTTP response.
     */
    private function processMiddleware(
        ServerRequestInterface $request,
        int $position,
        RequestHandlerInterface $finalHandler
    ): ResponseInterface {
        // If we've exhausted all middleware, use the final handler
        if (!isset($this->middlewares[$position])) {
            return $finalHandler->handle($request);
        }

        // Create a handler that will process the next middleware in the chain
        $nextPosition = $position + 1;
        $handler = new class($nextPosition, $finalHandler, function($req, $pos, $fh) {
            return $this->processMiddleware($req, $pos, $fh);
        }) implements RequestHandlerInterface {
            public function __construct(
                private readonly int $nextPosition,
                private readonly RequestHandlerInterface $finalHandler,
                private readonly \Closure $processor
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->processor)($request, $this->nextPosition, $this->finalHandler);
            }
        };

        return $this->middlewares[$position]->process($request, $handler);
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
    private function validateMiddleware(mixed $middleware, int|string $position): void
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
     * This static method allows the initialization of a Pipeline with a collection of middleware,
     * and optionally, a custom fallback handler. If no fallback handler is provided, the default
     * EmptyPipelineHandler is used.
     *
     * @param iterable<MiddlewareInterface> $middlewares An iterable collection of middleware objects.
     * @param ?RequestHandlerInterface $fallbackHandler The fallback handler to use.
     *
     * @return self Returns a fully configured Pipeline instance.
     *
     * @example
     * ```php
     * $pipeline = Pipeline::createFromIterable([
     *     new AuthMiddleware(),
     *     new ValidationMiddleware(),
     * ], new ApplicationHandler());
     * ```
     */
    public static function createFromIterable(iterable $middlewares, ?RequestHandlerInterface $fallbackHandler = null): PipelineInterface
    {
        return new self($middlewares, $fallbackHandler ?? new EmptyPipelineHandler);
    }

    /**
     * Returns a new Pipeline instance with the updated fallback handler.
     *
     * This method creates a clone of the current pipeline and replaces its fallback handler with the provided handler.
     * By cloning the existing pipeline, it ensures that the original pipeline remains unmodified, promoting immutability.
     *
     * @param RequestHandlerInterface $handler The new fallback handler to be used when the middleware chain is exhausted.
     *
     * @return PipelineInterface Returns the cloned pipeline instance with the updated fallback handler.
     *
     * @example
     * ```php
     * $pipeline = $pipeline->withFallbackHandler(new CustomHandler());
     * ```
     */
    public function withFallbackHandler(RequestHandlerInterface $handler): PipelineInterface
    {
        $clone = clone $this;
        $clone->fallbackHandler = $handler;

        return $clone;
    }
}
