<?php

namespace Bermuda\Http\Middleware;

use Traversable;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Interface PipelineInterface
 *
 * Represents a middleware pipeline that is capable of both processing a server request
 * (as a RequestHandlerInterface) and acting as an individual middleware component (as a MiddlewareInterface).
 * Additionally, it supports counting (via Countable) and iteration (via IteratorAggregate) over its middleware components.
 *
 * The pipeline maintains a sequence of PSR-15 middleware and processes HTTP requests through them.
 * This allows for flexible composition of middleware chains.
 *
 * Key features:
 * - Immutability: Methods like pipe() return new instances
 * - Composability: Pipelines can contain other pipelines
 * - Flexibility: Can act as both a handler and middleware
 * - PSR-15 compliant
 *
 * @example Creating and using a pipeline
 * ```php
 * $pipeline = new Pipeline([
 *     new AuthenticationMiddleware(),
 *     new LoggingMiddleware(),
 *     new RateLimitMiddleware(),
 * ], $applicationHandler);
 *
 * $response = $pipeline->handle($request);
 * ```
 *
 * @example Pipeline composition
 * ```php
 * $apiPipeline = new Pipeline([
 *     new JsonMiddleware(),
 *     new ValidationMiddleware(),
 * ]);
 *
 * $mainPipeline = new Pipeline([
 *     new CorsMiddleware(),
 *     $apiPipeline, // Pipeline as middleware
 * ], $handler);
 * ```
 *
 * @example Adding middleware dynamically
 * ```php
 * $pipeline = $basePipeline->pipe([
 *     new CacheMiddleware(),
 *     new CompressionMiddleware(),
 * ]);
 * ```
 */
interface PipelineInterface extends MiddlewareInterface, RequestHandlerInterface, \Countable, \IteratorAggregate
{
    /**
     * Adds one or more middleware to the pipeline.
     *
     * This method appends (or prepends if $prepend is true) the specified middleware to the current pipeline,
     * returning a new immutable PipelineInterface instance with the additional middleware.
     *
     * @param iterable|MiddlewareInterface $middlewares One or more middleware to add.
     * @param bool $prepend If true, adds the middleware at the beginning of the pipeline; otherwise, they are appended.
     *
     * @return PipelineInterface A new pipeline instance that includes the added middleware.
     *
     * @throws \InvalidArgumentException If any middleware doesn't implement MiddlewareInterface.
     * @throws \RuntimeException If circular reference is detected.
     *
     * @example Appending middleware
     * ```php
     * $pipeline = $pipeline->pipe([
     *     new CacheMiddleware(),
     *     new MetricsMiddleware(),
     * ]);
     * ```
     *
     * @example Prepending middleware (execute first)
     * ```php
     * $pipeline = $pipeline->pipe(new SecurityHeadersMiddleware(), prepend: true);
     * ```
     */
    public function pipe(iterable|MiddlewareInterface $middlewares, bool $prepend = false): PipelineInterface;

    /**
     * Returns an iterator for traversing the registered middleware.
     *
     * This method enables external code to iterate over the middleware in the pipeline (e.g., using foreach).
     *
     * @return Traversable<MiddlewareInterface> A traversable that yields each middleware in the pipeline.
     *
     * @example
     * ```php
     * foreach ($pipeline as $middleware) {
     *     echo get_class($middleware) . "\n";
     * }
     * ```
     */
    public function getIterator(): Traversable;

    /**
     * Checks if a specific middleware exists in the pipeline.
     *
     * The search can be performed either by passing in a middleware instance or by specifying its class name.
     *
     * @template T of MiddlewareInterface
     * @param MiddlewareInterface|class-string<T> $middleware The middleware instance or fully-qualified class name to search for.
     * @return bool Returns true if the middleware exists in the pipeline; false otherwise.
     *
     * @example Check by class name
     * ```php
     * if ($pipeline->has(AuthMiddleware::class)) {
     *     // Pipeline includes authentication middleware
     * }
     * ```
     *
     * @example Check by instance
     * ```php
     * $cache = new CacheMiddleware();
     * if ($pipeline->has($cache)) {
     *     // This specific instance is in the pipeline
     * }
     * ```
     */
    public function has(MiddlewareInterface|string $middleware): bool;

    /**
     * Checks whether the pipeline is empty.
     *
     * This method returns true if there are no middleware components registered in the pipeline,
     * indicating that the pipeline would immediately fall back to using the fallback handler if invoked.
     *
     * @return bool True if the pipeline is empty; false otherwise.
     *
     * @example
     * ```php
     * if ($pipeline->isEmpty()) {
     *     // Request will go directly to the fallback handler
     * }
     * ```
     */
    public function isEmpty(): bool;
}
