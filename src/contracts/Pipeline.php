<?php


namespace Lobster\Pipeline\Contracts;


use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Interface Pipeline
 * @package Lobster\Pipeline\Contracts
 */
interface Pipeline extends MiddlewareInterface, RequestHandlerInterface
{
    /**
     * @param MiddlewareInterface $middleware
     * @return Pipeline
     */
    public function pipe(MiddlewareInterface $middleware): Pipeline ;
}