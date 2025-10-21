<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class PipelineFactory
 *
 * Default implementation of PipelineFactoryInterface for creating middleware pipelines.
 * 
 * This factory provides a straightforward way to instantiate Pipeline objects,
 * making it easy to inject pipeline creation as a dependency. It can be extended
 * or composed with other services (like DI containers) for more advanced scenarios.
 *
 * Key features:
 * - Simple, stateless factory implementation
 * - No dependencies required
 * - Uses EmptyPipelineHandler as default fallback
 * - Can be easily extended for custom behavior
 *
 * @example Basic usage
 * ```php
 * $factory = new PipelineFactory();
 * 
 * $pipeline = $factory->createMiddlewarePipeline([
 *     new AuthMiddleware(),
 *     new LoggingMiddleware(),
 *     new RouteMiddleware(),
 * ], new ApplicationHandler());
 * 
 * $response = $pipeline->handle($request);
 * ```
 *
 * @example Dependency injection
 * ```php
 * class Application
 * {
 *     public function __construct(
 *         private PipelineFactoryInterface $pipelineFactory
 *     ) {}
 *     
 *     public function createApiPipeline(): PipelineInterface
 *     {
 *         return $this->pipelineFactory->createMiddlewarePipeline([
 *             new CorsMiddleware(),
 *             new JsonMiddleware(),
 *             new ApiAuthMiddleware(),
 *         ]);
 *     }
 * }
 * ```
 *
 * @example Testing with factory
 * ```php
 * class PipelineTest extends TestCase
 * {
 *     private PipelineFactoryInterface $factory;
 *     
 *     protected function setUp(): void
 *     {
 *         $this->factory = new PipelineFactory();
 *     }
 *     
 *     public function testPipelineExecution(): void
 *     {
 *         $pipeline = $this->factory->createMiddlewarePipeline([
 *             new TestMiddleware(),
 *         ], new TestHandler());
 *         
 *         // ... test assertions
 *     }
 * }
 * ```
 */
final class PipelineFactory implements PipelineFactoryInterface
{
    /**
     * Creates a new middleware pipeline instance.
     *
     * This method constructs a fresh Pipeline with the provided middleware
     * collection and fallback handler. If no fallback handler is specified,
     * the pipeline will use EmptyPipelineHandler, which throws an exception
     * if the empty pipeline is executed.
     *
     * @param iterable<MiddlewareInterface> $middlewares Optional collection of middleware 
     *                                                    to include in the pipeline. Can be 
     *                                                    an array, iterator, or generator.
     * @param RequestHandlerInterface|null $fallbackHandler Optional handler to use when the 
     *                                                       middleware chain completes. If null,
     *                                                       EmptyPipelineHandler is used.
     *
     * @return PipelineInterface A new Pipeline instance ready to process requests.
     *
     * @throws \InvalidArgumentException If any middleware in the collection is invalid
     *                                  (thrown by Pipeline constructor).
     *
     * @example Creating an empty pipeline
     * ```php
     * $factory = new PipelineFactory();
     * $pipeline = $factory->createMiddlewarePipeline();
     * 
     * // Will throw exception when handle() is called without middleware
     * ```
     *
     * @example Creating a pipeline with middleware
     * ```php
     * $factory = new PipelineFactory();
     * $pipeline = $factory->createMiddlewarePipeline([
     *     new SecurityHeadersMiddleware(),
     *     new CompressionMiddleware(),
     * ], new ApplicationHandler());
     * 
     * $response = $pipeline->handle($request);
     * ```
     *
     * @example Creating a pipeline from generator
     * ```php
     * $factory = new PipelineFactory();
     * 
     * $middlewares = function() {
     *     yield new Middleware1();
     *     yield new Middleware2();
     *     yield new Middleware3();
     * };
     * 
     * $pipeline = $factory->createMiddlewarePipeline($middlewares());
     * ```
     *
     * @example Using with default handler
     * ```php
     * $factory = new PipelineFactory();
     * 
     * // Pipeline will use EmptyPipelineHandler by default
     * $pipeline = $factory->createMiddlewarePipeline([
     *     new Middleware1(),
     * ]);
     * ```
     */
    public function createMiddlewarePipeline(
        iterable $middlewares = [],
        ?RequestHandlerInterface $fallbackHandler = null
    ): PipelineInterface {
        return new Pipeline($middlewares, $fallbackHandler ?? new EmptyPipelineHandler());
    }
}
