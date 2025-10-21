<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Interface PipelineFactoryInterface
 *
 * Factory interface for creating middleware pipeline instances.
 * 
 * This interface defines a standard way to instantiate middleware pipelines,
 * allowing for dependency injection and easier testing. Implementations can
 * provide custom pipeline configurations, middleware resolution from containers,
 * or apply default middleware to all created pipelines.
 *
 * @example Basic factory usage
 * ```php
 * class PipelineFactory implements PipelineFactoryInterface
 * {
 *     public function createMiddlewarePipeline(
 *         iterable $middlewares = [],
 *         ?RequestHandlerInterface $fallbackHandler = null
 *     ): PipelineInterface {
 *         return new Pipeline($middlewares, $fallbackHandler ?? new DefaultHandler());
 *     }
 * }
 * 
 * $factory = new PipelineFactory();
 * $pipeline = $factory->createMiddlewarePipeline([
 *     new AuthMiddleware(),
 *     new LoggingMiddleware(),
 * ]);
 * ```
 *
 * @example Factory with container integration
 * ```php
 * class ContainerAwarePipelineFactory implements PipelineFactoryInterface
 * {
 *     public function __construct(private ContainerInterface $container) {}
 *     
 *     public function createMiddlewarePipeline(
 *         iterable $middlewares = [],
 *         ?RequestHandlerInterface $fallbackHandler = null
 *     ): PipelineInterface {
 *         $resolved = [];
 *         foreach ($middlewares as $middleware) {
 *             $resolved[] = is_string($middleware) 
 *                 ? $this->container->get($middleware)
 *                 : $middleware;
 *         }
 *         
 *         return new Pipeline($resolved, $fallbackHandler);
 *     }
 * }
 * ```
 *
 * @example Factory with default middleware
 * ```php
 * class ApiPipelineFactory implements PipelineFactoryInterface
 * {
 *     public function createMiddlewarePipeline(
 *         iterable $middlewares = [],
 *         ?RequestHandlerInterface $fallbackHandler = null
 *     ): PipelineInterface {
 *         // Always prepend CORS and error handling for API routes
 *         $defaultMiddlewares = [
 *             new CorsMiddleware(),
 *             new ErrorHandlerMiddleware(),
 *         ];
 *         
 *         return new Pipeline(
 *             array_merge($defaultMiddlewares, iterator_to_array($middlewares)),
 *             $fallbackHandler
 *         );
 *     }
 * }
 * ```
 */
interface PipelineFactoryInterface
{
    /**
     * Creates a new middleware pipeline instance.
     *
     * This method constructs a fresh PipelineInterface instance with the specified
     * middleware collection and fallback handler. The factory may apply additional
     * configuration, resolve middleware from a container, or add default middleware
     * depending on the implementation.
     *
     * @param iterable<MiddlewareInterface> $middlewares Optional collection of middleware 
     *                                                    to include in the pipeline. Can be 
     *                                                    an array, iterator, or generator.
     * @param RequestHandlerInterface|null $fallbackHandler Optional handler to use when the 
     *                                                       middleware chain completes. If null,
     *                                                       the implementation should provide
     *                                                       a sensible default.
     *
     * @return PipelineInterface A new pipeline instance ready to process requests.
     *
     * @throws \InvalidArgumentException If any middleware in the collection is invalid.
     *
     * @example Creating an empty pipeline
     * ```php
     * $pipeline = $factory->createMiddlewarePipeline();
     * ```
     *
     * @example Creating a pipeline with middleware
     * ```php
     * $pipeline = $factory->createMiddlewarePipeline([
     *     new SecurityHeadersMiddleware(),
     *     new CompressionMiddleware(),
     * ]);
     * ```
     *
     * @example Creating a pipeline with custom handler
     * ```php
     * $pipeline = $factory->createMiddlewarePipeline(
     *     [$middleware1, $middleware2],
     *     new CustomApplicationHandler()
     * );
     * ```
     */
    public function createMiddlewarePipeline(
        iterable $middlewares = [],
        ?RequestHandlerInterface $fallbackHandler = null
    ): PipelineInterface;
}
