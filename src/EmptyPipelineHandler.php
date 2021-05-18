<?php

namespace Bermuda\Pipeline;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class EmptyPipelineHandler
 * @package Bermuda\Pipeline
 */
final class EmptyPipelineHandler implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \RuntimeException('Failed to process the request. The pipeline is empty!');
    }
}
