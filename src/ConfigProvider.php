<?php

namespace Bermuda\Pipeline;

use Psr\Container\ContainerInterface;

/**
 * Class ConfigProvider
 * @package Bermuda\Pipeline
 */
final class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    /**
     * @inheritDoc
     */
    protected function getFactories(): array
    {
        return [PipelineInterface::class => PipelineFactoryInterface::class];
    }
    
    /**
     * @inheritDoc
     */
    protected function getInvokables(): array
    {
        return [PipelineFactoryInterface::class => PipelineFactory::class];
    }
}
