<?php


namespace Lobster\Pipeline;


use Psr\Http\Server\MiddlewareInterface;

/**
 * Class Queue
 * @package Lobster\Pipeline
 */
final class Queue
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middleware = [];

    /**
     * @param MiddlewareInterface $middleware
     */
    public function enqueue(MiddlewareInterface $middleware) : void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * @return MiddlewareInterface
     */
    public function dequeue() :? MiddlewareInterface
    {
        return array_shift($this->middleware);
    }
}