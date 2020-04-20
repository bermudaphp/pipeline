<?php


namespace Lobster\Pipeline;


use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class PipelineFactory
 * @package Lobster\Pipeline
 */
class PipelineFactory implements Contracts\PipelineFactory
{
    /**
     * @param MiddlewareInterface[] $middleware
     * @param RequestHandlerInterface|null $handler
     * @return Pipeline
     */
    public function __invoke(iterable $middleware = [], RequestHandlerInterface $handler = null) : Pipeline
    {

        $pipeline = new Pipeline($handler);

        foreach ($middleware as $item)
        {
            $pipeline->pipe($item);
        }

        return $pipeline;
    }
}