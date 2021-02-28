<?php

namespace Bermuda\Pipeline;

use Psr\Container\ContainerInterface;

/**
 * Class ConfigProvider
 * @package Bermuda\Pipeline
 */
final class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    protected function getFactories(): array
    {
        return [PipelineInterface::class => PipelineFactoryInterface::class];
    }
    
    protected function getInvokables(): array
    {
        return [PipelineFactoryInterface::class => PipelineFactory::class];
    }
}
