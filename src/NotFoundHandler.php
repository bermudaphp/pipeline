<?php

namespace Bermuda\Pipeline;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Class NotFoundHandler
 * @package Bermuda\Pipeline
 */
final class NotFoundHandler implements RequestHandlerInterface
{
    private ResponseFactoryInterface $factory;
    
    public function __construct(ResponseFactoryInterface $factory)
    {
        $this->factory = $factory;
    }
    
    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->factory->createResponce(404, 'Not Found!');
    }
}
