<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware;

use SplQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class Next
 *
 * Internal handler that manages sequential middleware execution through a queue.
 * 
 * This class is the core mechanism that powers the Pipeline's middleware processing.
 * It implements a queue-based approach where each middleware is dequeued and processed
 * in order. When the queue is exhausted, control is passed to the final handler.
 *
 * Key design decisions:
 * - **Queue cloning**: Prevents side effects by working on a copy of the original queue
 * - **Stateless processing**: Each Next instance is immutable after construction
 * - **Recursive delegation**: Each middleware receives a fresh Next instance as its handler
 * - **Thread-safe design**: No shared mutable state between invocations
 *
 * The queue-based approach eliminates the need for position tracking or cursor
 * management, making the implementation simpler and more reliable.
 *
 * @internal This class is for internal use by the Pipeline implementation only.
 *           It should not be used directly by application code.
 *
 * @example How Next processes middleware (conceptual)
 * ```php
 * // Initial state: queue = [M1, M2, M3], finalHandler = H
 * $next = new Next($queue, $finalHandler);
 * 
 * // First call: next->handle()
 * // - Dequeues M1
 * // - Calls M1->process($request, $next)
 * // - Inside M1: calls $handler->handle() which is $next->handle()
 * 
 * // Second call: next->handle()
 * // - Dequeues M2
 * // - Calls M2->process($request, $next)
 * // - Inside M2: calls $handler->handle() which is $next->handle()
 * 
 * // Third call: next->handle()
 * // - Dequeues M3
 * // - Calls M3->process($request, $next)
 * // - Inside M3: calls $handler->handle() which is $next->handle()
 * 
 * // Fourth call: next->handle()
 * // - Queue is empty
 * // - Calls $finalHandler->handle($request)
 * // - Returns response
 * ```
 *
 * @example Middleware short-circuit behavior
 * ```php
 * class AuthMiddleware implements MiddlewareInterface
 * {
 *     public function process(
 *         ServerRequestInterface $request,
 *         RequestHandlerInterface $handler
 *     ): ResponseInterface {
 *         if (!$this->isAuthenticated($request)) {
 *             // Return immediately without calling $handler->handle()
 *             // Remaining middleware in the queue are never executed
 *             return new Response(401, [], 'Unauthorized');
 *         }
 *         
 *         // Continue to next middleware
 *         return $handler->handle($request);
 *     }
 * }
 * ```
 *
 * @example Why queue cloning is critical
 * ```php
 * // Without cloning (problematic):
 * $queue = new SplQueue();
 * $queue->enqueue($m1);
 * $queue->enqueue($m2);
 * 
 * $next1 = new Next($queue, $handler); // If not cloned
 * $next1->handle($request); // Modifies the original $queue
 * 
 * $next2 = new Next($queue, $handler); // Queue is now empty!
 * $next2->handle($request); // Would skip all middleware
 * 
 * // With cloning (correct):
 * $next1 = new Next($queue, $handler); // Clones queue internally
 * $next1->handle($request); // Works on copy
 * 
 * $next2 = new Next($queue, $handler); // Gets fresh copy
 * $next2->handle($request); // Works correctly
 * ```
 */
final class Next implements RequestHandlerInterface
{
    /**
     * Internal queue of middleware to be processed.
     * 
     * This is a cloned copy of the original queue passed to the constructor,
     * ensuring that processing this Next instance does not affect the original
     * queue or other Next instances.
     *
     * @var SplQueue<MiddlewareInterface>
     */
    private SplQueue $queue;

    /**
     * Constructs a new Next handler instance.
     *
     * The constructor clones the provided queue to ensure isolation. This prevents
     * the sequential dequeue operations during request processing from affecting
     * the original queue or other Next instances created from the same queue.
     *
     * This design allows the Pipeline to safely create multiple Next instances
     * from the same middleware queue without side effects.
     *
     * @param SplQueue<MiddlewareInterface> $queue The queue of middleware to process.
     *                                             This queue is cloned internally to
     *                                             prevent external modifications.
     * @param RequestHandlerInterface $finalHandler The handler to invoke when all 
     *                                              middleware have been processed.
     *                                              This is typically the application
     *                                              handler or Pipeline's fallback handler.
     *
     * @example Construction in Pipeline context
     * ```php
     * // Inside Pipeline::handle()
     * $next = new Next($this->middlewares, $this->fallbackHandler);
     * return $next->handle($request);
     * ```
     */
    public function __construct(
        SplQueue $queue,
        private readonly RequestHandlerInterface $finalHandler
    ) {
        $this->queue = clone $queue;
    }

    /**
     * Handles a server request by processing the next middleware in the queue.
     *
     * This method implements the core middleware execution logic:
     * 
     * 1. If the queue is empty, delegate to the final handler (base case)
     * 2. Otherwise, dequeue the next middleware
     * 3. Pass the request to the middleware along with $this as the next handler
     * 4. The middleware can then choose to:
     *    - Call $handler->handle($request) to continue the chain
     *    - Return a response immediately to short-circuit the chain
     * 
     * This recursive delegation ensures that each middleware has full control
     * over whether to continue the chain or terminate early.
     *
     * @param ServerRequestInterface $request The server request to process through
     *                                       the middleware chain.
     *
     * @return ResponseInterface The HTTP response, either from a middleware that
     *                          short-circuited the chain or from the final handler
     *                          after all middleware have been processed.
     *
     * @example Normal execution flow
     * ```php
     * $m1 = new LoggingMiddleware();
     * $m2 = new ValidationMiddleware();
     * $m3 = new RouteMiddleware();
     * 
     * $queue = new SplQueue();
     * $queue->enqueue($m1);
     * $queue->enqueue($m2);
     * $queue->enqueue($m3);
     * 
     * $next = new Next($queue, $finalHandler);
     * 
     * // Call stack:
     * // 1. next->handle()           -> dequeues m1
     * // 2. m1->process(req, next)   -> logs, calls handler->handle()
     * // 3. next->handle()           -> dequeues m2
     * // 4. m2->process(req, next)   -> validates, calls handler->handle()
     * // 5. next->handle()           -> dequeues m3
     * // 6. m3->process(req, next)   -> routes, calls handler->handle()
     * // 7. next->handle()           -> queue empty
     * // 8. finalHandler->handle()   -> returns response
     * ```
     *
     * @example Short-circuit execution
     * ```php
     * class CacheMiddleware implements MiddlewareInterface
     * {
     *     public function process($request, $handler): ResponseInterface
     *     {
     *         if ($cached = $this->cache->get($request)) {
     *             return $cached; // Short-circuit - no further processing
     *         }
     *         
     *         $response = $handler->handle($request);
     *         $this->cache->set($request, $response);
     *         return $response;
     *     }
     * }
     * 
     * // If cache hits, remaining middleware and final handler are skipped
     * ```
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->queue->isEmpty()) {
            return $this->finalHandler->handle($request);
        }

        $middleware = $this->queue->dequeue();
        return $middleware->process($request, $this);
    }
}
