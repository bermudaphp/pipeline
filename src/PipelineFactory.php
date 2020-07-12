<?php


namespace Bermuda\Pipeline;


use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class PipelineFactory
 * @package Bermuda\Pipeline
 */
final class PipelineFactory implements PipelineFactoryInterface
{
    /**
     * @param MiddlewareInterface[] $middleware
     * @param RequestHandlerInterface|null $handler
     * @return PipelineInterface
     */
    public function make(?iterable $middleware = [], RequestHandlerInterface $handler = null) : PipelineInterface
    {
        $pipeline = new Pipeline($handler);

        foreach ($middleware as $item)
        {
            $pipeline->pipe($item);
        }

        return $pipeline;
    }
}
