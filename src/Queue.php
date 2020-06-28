<?php


namespace Bermuda\Pipeline;


use Psr\Http\Server\MiddlewareInterface;


/**
 * Class Queue
 * @package Bermuda\Pipeline
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
     * @return MiddlewareInterface|null
     */
    public function dequeue() :? MiddlewareInterface
    {
        return array_shift($this->middleware);
    }
}
