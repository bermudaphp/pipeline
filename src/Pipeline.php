<?php

declare(strict_types=1);

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
     * Initializes the pipeline with a collection of middleware and a fallback handler.
     * The middleware are validated during construction to ensure type safety, and any
     * invalid middleware will cause an exception to be thrown immediately.
     *
     * @param iterable<MiddlewareInterface> $middlewares An iterable collection of middleware objects.
     *                                                   Can be an array, iterator, or generator.
     * @param RequestHandlerInterface $fallbackHandler The fallback handler to use when the middleware 
     *                                                 chain is exhausted. Defaults to EmptyPipelineHandler
     *                                                 which throws an exception if reached.
     *
     * @throws \InvalidArgumentException If any middleware does not implement MiddlewareInterface.
     *
     * @example Creating a pipeline with array
     * ```php
     * $pipeline = new Pipeline([
     *     new Middleware1(),
     *     new Middleware2(),
     * ], $handler);
     * ```
     *
     * @example Creating a pipeline with generator
     * ```php
     * $middlewares = function() {
     *     yield new Middleware1();
     *     yield new Middleware2();
     * };
     * $pipeline = new Pipeline($middlewares(), $handler);
     * ```
     */
    public function __construct(
        iterable $middlewares = [],
        private(set) RequestHandlerInterface $fallbackHandler = new EmptyPipelineHandler
    ) {
        $this->middlewares = new \SplQueue();
        
        foreach ($middlewares as $i => $middleware) {
            $this->isMiddlewareInstance($middleware, $i);
            $this->middlewares->enqueue($middleware);
        }
    }

    /**
     * Checks if a specific middleware exists in the pipeline.
     *
     * Supports two search modes:
     * 1. By instance (identity comparison using ===)
     * 2. By fully-qualified class name (string comparison)
     *
     * This method is useful for conditional logic based on pipeline composition,
     * such as avoiding duplicate middleware or checking for required middleware.
     *
     * @template T of MiddlewareInterface
     * @param MiddlewareInterface|class-string<T> $middleware The middleware instance or class to search for.
     *
     * @return bool Returns true if the middleware exists; false otherwise.
     *
     * @example Checking by instance
     * ```php
     * $auth = new AuthMiddleware();
     * $pipeline = new Pipeline([$auth]);
     * 
     * if ($pipeline->has($auth)) {
     *     echo "Pipeline has this specific auth instance";
     * }
     * ```
     *
     * @example Checking by class name
     * ```php
     * $pipeline = new Pipeline([new AuthMiddleware()]);
     * 
     * if ($pipeline->has(AuthMiddleware::class)) {
     *     echo "Pipeline has auth middleware";
     * }
     * ```
     *
     * @example Avoiding duplicates
     * ```php
     * if (!$pipeline->has(CorsMiddleware::class)) {
     *     $pipeline = $pipeline->pipe(new CorsMiddleware());
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
     * $pipeline = new Pipeline();
     * 
     * if ($pipeline->isEmpty()) {
     *     echo "Pipeline has no middleware";
     * }
     * ```
     */
    public function isEmpty(): bool
    {
        return $this->middlewares->isEmpty();
    }

    /**
     * Returns the number of middleware objects in the pipeline.
     *
     * Implements the Countable interface, allowing the pipeline to be used
     * with count() function and counted in foreach loops.
     *
     * @return int The count of middleware components.
     *
     * @example
     * ```php
     * $pipeline = new Pipeline([$m1, $m2, $m3]);
     * echo count($pipeline); // Output: 3
     * ```
     */
    public function count(): int
    {
        return $this->middlewares->count();
    }

    /**
     * Retrieves an external iterator over the middleware collection.
     *
     * Implements the IteratorAggregate interface, allowing the pipeline to be
     * traversed in foreach loops. Yields middleware in the order they will be
     * executed (FIFO - First In, First Out).
     *
     * @return \Generator<MiddlewareInterface> A generator that yields each middleware in the pipeline.
     *
     * @example Iterating over middleware
     * ```php
     * foreach ($pipeline as $middleware) {
     *     echo get_class($middleware) . "\n";
     * }
     * ```
     *
     * @example Inspecting pipeline composition
     * ```php
     * $middlewareClasses = array_map(
     *     fn($m) => $m::class,
     *     iterator_to_array($pipeline)
     * );
     * print_r($middlewareClasses);
     * ```
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
     * Clones the queue structure and the fallback handler to ensure complete
     * isolation between the original and cloned pipeline. Middleware themselves
     * are not cloned as they should be stateless by design.
     *
     * This method is called automatically by PHP when using the clone keyword.
     *
     * @return void
     *
     * @example Cloning behavior
     * ```php
     * $original = new Pipeline([$m1, $m2]);
     * $clone = clone $original;
     * 
     * // $clone has its own queue, independent of $original
     * $clone = $clone->pipe($m3);
     * 
     * echo count($original); // 2
     * echo count($clone);    // 3
     * ```
     */
    public function __clone(): void
    {
        $this->middlewares = clone $this->middlewares;
        $this->fallbackHandler = clone $this->fallbackHandler;
    }

    /**
     * Adds additional middleware to the pipeline.
     *
     * Creates a new immutable Pipeline instance with the added middleware,
     * leaving the original pipeline unchanged. Middleware can be appended
     * to the end (default) or prepended to the beginning of the chain.
     *
     * This method validates middleware and prevents circular references
     * that would cause infinite loops during request processing.
     *
     * @param iterable|MiddlewareInterface $middlewares One or more middleware to add.
     *                                                  Can be a single middleware instance
     *                                                  or an iterable collection.
     * @param bool $prepend If true, adds the middleware at the beginning of the chain;
     *                     otherwise, they are appended to the end.
     *
     * @return PipelineInterface A new Pipeline instance with the added middleware.
     *
     * @throws \InvalidArgumentException If any provided middleware does not implement MiddlewareInterface.
     * @throws \RuntimeException If middleware refers to the pipeline itself (direct circular reference).
     * @throws \RuntimeException If middleware is a pipeline that contains reference to this pipeline
     *                          (indirect circular reference).
     *
     * @example Appending middleware (default behavior)
     * ```php
     * $pipeline = new Pipeline([$middleware1]);
     * $newPipeline = $pipeline->pipe([$middleware2, $middleware3]);
     * 
     * // Execution order: middleware1 -> middleware2 -> middleware3
     * ```
     *
     * @example Prepending middleware (execute first)
     * ```php
     * $pipeline = new Pipeline([$middleware2]);
     * $newPipeline = $pipeline->pipe($middleware1, prepend: true);
     * 
     * // Execution order: middleware1 -> middleware2
     * ```
     *
     * @example Piping single middleware
     * ```php
     * $pipeline = $pipeline->pipe(new CorsMiddleware());
     * ```
     *
     * @example Building pipeline fluently
     * ```php
     * $pipeline = (new Pipeline())
     *     ->pipe(new ErrorHandlerMiddleware())
     *     ->pipe(new CorsMiddleware())
     *     ->pipe(new AuthMiddleware())
     *     ->pipe(new RouteMiddleware());
     * ```
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
     * This method allows the pipeline to act as a PSR-15 middleware component.
     * It processes the request through its internal middleware chain and then
     * delegates to the provided handler instead of using the fallback handler.
     *
     * This is useful when composing pipelines or when you want to use a pipeline
     * as middleware within another pipeline.
     *
     * @param ServerRequestInterface $request The server request to process.
     * @param RequestHandlerInterface $handler The handler to use when middleware chain is exhausted.
     *
     * @return ResponseInterface The HTTP response.
     *
     * @throws \RuntimeException If handler is the pipeline itself, which would cause all
     *                          middleware to execute twice (double execution bug).
     *
     * @example Using pipeline as middleware
     * ```php
     * $apiPipeline = new Pipeline([
     *     new JsonMiddleware(),
     *     new ApiValidationMiddleware(),
     * ]);
     * 
     * $mainPipeline = new Pipeline([
     *     new ErrorHandlerMiddleware(),
     *     $apiPipeline, // Using pipeline as middleware
     *     new RouteMiddleware(),
     * ], $applicationHandler);
     * 
     * // Request flows through:
     * // 1. ErrorHandlerMiddleware
     * // 2. JsonMiddleware (from apiPipeline)
     * // 3. ApiValidationMiddleware (from apiPipeline)
     * // 4. RouteMiddleware
     * // 5. applicationHandler
     * ```
     *
     * @example With custom handler
     * ```php
     * $response = $pipeline->process($request, $customHandler);
     * ```
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
     * This method allows the pipeline to act as a PSR-15 request handler.
     * It processes the request through all middleware in order, and when the
     * chain is exhausted, delegates to the fallback handler configured during
     * construction.
     *
     * @param ServerRequestInterface $request The server request to handle.
     *
     * @return ResponseInterface The HTTP response.
     *
     * @throws \RuntimeException If the pipeline is empty and using the default
     *                          EmptyPipelineHandler, which throws an exception.
     *
     * @example Handling a request
     * ```php
     * $pipeline = new Pipeline([
     *     new AuthMiddleware(),
     *     new RouteMiddleware(),
     * ], new ApplicationHandler());
     * 
     * $response = $pipeline->handle($request);
     * ```
     *
     * @example Empty pipeline error
     * ```php
     * $pipeline = new Pipeline(); // No middleware, uses EmptyPipelineHandler
     * 
     * try {
     *     $response = $pipeline->handle($request);
     * } catch (\RuntimeException $e) {
     *     echo $e->getMessage(); // "Failed to process the request. The pipeline is empty!"
     * }
     * ```
     */
    public function handle(ServerRequestInterface $request): ResponseInterface 
    {
        $next = new Next($this->middlewares, $this->fallbackHandler);
        return $next->handle($request);
    }

    /**
     * Validates that the given value is a middleware instance.
     *
     * This is a private validation method used during construction to ensure
     * type safety. It throws a descriptive exception if validation fails,
     * including the position of the invalid middleware for easier debugging.
     *
     * @param mixed $middleware The value to validate.
     * @param int|string $position The position of the middleware in the collection,
     *                            used for error reporting.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the value does not implement MiddlewareInterface.
     *                                  The exception message includes the position and actual type.
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
     * Validates that the given value is a middleware instance (alias for validateMiddleware).
     *
     * This method performs the same validation as validateMiddleware() but is used
     * in the pipe() method context for consistency.
     *
     * @param mixed $middleware The value to validate.
     * @param int|string $position The position of the middleware in the collection.
     *
     * @return void
     *
     * @throws \InvalidArgumentException If the value does not implement MiddlewareInterface.
     *
     * @see validateMiddleware()
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
     * This is a named constructor (factory method) that provides a more explicit
     * way to create pipelines from iterable sources. It's functionally equivalent
     * to using the constructor directly but can improve code readability.
     *
     * @param iterable<MiddlewareInterface> $middlewares An iterable collection of middleware objects.
     * @param RequestHandlerInterface|null $fallbackHandler The fallback handler to use. If null,
     *                                                      defaults to EmptyPipelineHandler.
     *
     * @return PipelineInterface Returns a fully configured Pipeline instance.
     *
     * @throws \InvalidArgumentException If any middleware does not implement MiddlewareInterface.
     *
     * @example Creating from array
     * ```php
     * $pipeline = Pipeline::createFromIterable([
     *     new Middleware1(),
     *     new Middleware2(),
     * ], $handler);
     * ```
     *
     * @example Creating from generator
     * ```php
     * $middlewares = function() {
     *     foreach ($config['middlewares'] as $class) {
     *         yield $container->get($class);
     *     }
     * };
     * 
     * $pipeline = Pipeline::createFromIterable($middlewares());
     * ```
     */
    public static function createFromIterable(
        iterable $middlewares,
        ?RequestHandlerInterface $fallbackHandler = null
    ): PipelineInterface {
        return new self($middlewares, $fallbackHandler ?? new EmptyPipelineHandler);
    }

    /**
     * Returns a new Pipeline instance with the updated fallback handler.
     *
     * Creates a clone of the pipeline with a different fallback handler,
     * leaving the original pipeline unchanged. This is useful for creating
     * pipeline variants with different terminal handlers.
     *
     * @param RequestHandlerInterface $handler The new fallback handler to be used 
     *                                         when the middleware chain is exhausted.
     *
     * @return PipelineInterface Returns the cloned pipeline instance with the updated fallback handler.
     *
     * @example Replacing the fallback handler
     * ```php
     * $basePipeline = new Pipeline([
     *     new AuthMiddleware(),
     *     new ValidationMiddleware(),
     * ]); // Uses EmptyPipelineHandler by default
     * 
     * $webPipeline = $basePipeline->withFallbackHandler(new WebApplicationHandler());
     * $apiPipeline = $basePipeline->withFallbackHandler(new ApiApplicationHandler());
     * 
     * // $basePipeline remains unchanged
     * // $webPipeline uses WebApplicationHandler
     * // $apiPipeline uses ApiApplicationHandler
     * ```
     *
     * @example Building pipeline fluently
     * ```php
     * $pipeline = (new Pipeline())
     *     ->pipe(new AuthMiddleware())
     *     ->pipe(new RouteMiddleware())
     *     ->withFallbackHandler(new ApplicationHandler());
     * ```
     */
    public function withFallbackHandler(RequestHandlerInterface $handler): PipelineInterface
    {
        $clone = clone $this;
        $clone->fallbackHandler = $handler;

        return $clone;
    }
}
