<?php

namespace Bermuda\Pipeline;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class PipelineFactory
 * @package Bermuda\Pipeline
 */
class PipelineFactory implements PipelineFactoryInterface
{
    public const containerMiddlewareId = '\Bermuda\Pipeline\PipelineFactory@middlewareId';
    public const containerFallbackHandlerId = '\Bermuda\Pipeline\PipelineFactory@fallbackHandlerId';

    /**
     * @param ContainerInterface $container
     * @param RequestHandlerInterface|null $handler
     * @return Pipeline
     */
    public function __invoke(ContainerInterface $container): PipelineInterface
    {
        return $this->make(
            $container->has(self::containerMiddlewareId) ?
                $container->get(self::containerMiddlewareId) : null,
            $container->has(self::containerFallbackHandlerId) ?
                $container->get(self::containerFallbackHandlerId) : null
        );
    }

    /**
     * @param MiddlewareInterface[]|null $middleware
     * @param RequestHandlerInterface|null $fallbackHandler
     * @return Pipeline
     */
    public function make(?iterable $middleware = [], ?RequestHandlerInterface $fallbackHandler = null): PipelineInterface
    {
        return !empty($middleware) ? Pipeline::makeOf($middleware, $fallbackHandler) : new Pipeline($fallbackHandler);
    }
}
