<?php

declare(strict_types=1);

namespace Bermuda\Http\Middleware\Tests;

use Bermuda\Http\Middleware\EmptyPipelineHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test suite for EmptyPipelineHandler.
 * 
 * Verifies that the handler correctly throws an exception when invoked,
 * which signals that a pipeline with no middleware attempted to process
 * a request. This is a safety mechanism to catch configuration errors
 * where a pipeline is used without any middleware being registered.
 * 
 * The single test case is sufficient as this class has only one
 * responsibility: throw a descriptive exception.
 */
final class EmptyPipelineHandlerTest extends TestCase
{
    #[Test]
    public function itThrowsRuntimeExceptionWhenHandlingRequest(): void
    {
        $handler = new EmptyPipelineHandler();
        $request = $this->createMock(ServerRequestInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to process the request. The pipeline is empty!');

        $handler->handle($request);
    }
}
