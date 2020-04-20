<?php


namespace Lobster\Pipeline;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;


/**
 * Class EmptyHandler
 * @package Lobster\Pipeline
 */
final class EmptyHandler implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('Empty pipeline handler');
    }
}