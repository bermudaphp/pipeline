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
 * This class is responsible for processing a server request through a chain of middleware.
 * It implements the PipelineInterface and provides methods for:
 * - Adding middleware via the pipe method.
 * - Sequentially processing a request via the process method.
 * - Handling a request with a fallback handler when the middleware chain is exhausted.
 */
final class Pipeline implements PipelineInterface
{
    /**
     * Current position in the middleware chain.
     *
     * @var int
     */
    private int $position = 0;

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
     *         Error Message: "Provided middleware does not implement MiddlewareInterface"
     */
    public function __construct(
        iterable $middlewares = [],
        private(set) RequestHandlerInterface $fallbackHandler = new EmptyPipelineHandler
    ) {
        foreach ($middlewares as $i => $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException("Provided middlewares ($i) does not implement " . MiddlewareInterface::class);
            }

            $this->middlewares[] = $middleware;
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
        if ($middleware instanceof MiddlewareInterface) $middleware = $middleware::class;

        return array_any($this->middlewares,
            static fn (MiddlewareInterface $m) => $middleware === $m::class
        );
    }

    /**
     * Checks if the pipeline is empty.
     *
     * @return bool True if there are no middleware registered; false otherwise.
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
     */
    public function getIterator(): \Generator
    {
        yield from $this->middlewares;
    }

    /**
     * Performs a deep clone of the pipeline.
     *
     * When cloning, each registered middleware is also cloned to prevent shared state issues.
     */
    public function __clone()
    {
        $this->position = 0;
        foreach ($this->middlewares as $i => $middleware) $this->middlewares[$i] = clone $middleware ;
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
     */
    public function pipe(iterable|MiddlewareInterface $middlewares, bool $prepend = false): PipelineInterface
    {
        $copy = clone $this;

        if ($middlewares instanceof MiddlewareInterface) $middlewares = [$middlewares];

        foreach ($middlewares as $i => $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new \InvalidArgumentException("Provided middlewares ($i) does not implement " . MiddlewareInterface::class);
            }

            if ($middleware === $this) {
                throw new \RuntimeException('Middleware cannot be the pipeline itself');
            }

            if ($prepend) array_unshift($copy->middlewares, $middleware);
            else $copy->middlewares[] = $middleware;
        }

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $middleware = $this->middlewares[$this->position++] ?? null ;

        try {
            if (!$middleware) {
                return $handler->handle($request);
            }

            return $middleware->process($request, $this);
        } finally { $this->position = 0; }
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface 
    {
        return $this->process($request, $this->fallbackHandler);
    }

    /**
     * Creates a new Pipeline instance from a set of middleware provided as an iterable.
     *
     * This static method allows the initialization of a Pipeline with a collection of middleware,
     * and optionally, a custom fallback handler. If no fallback handler is provided, the default
     * EmptyPipelineHandler is used.
     *
     * @param iterable<MiddlewareInterface> $middlewares       An iterable collection of middleware objects.
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
     * This method creates a clone of the current pipeline and replaces its fallback handler with the provided handler.
     * By cloning the existing pipeline, it ensures that the original pipeline remains unmodified, promoting immutability.
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
