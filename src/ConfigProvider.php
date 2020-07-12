<?php


namespace Bermuda\Pipeline;


final class ConfigProvider
{
    public function __invoke(): array
    {
        return ['dependencies' => [
                    'aliases' => [PipelineInterface::class => PipelineFactoryInterface::class], 
                    'invokables' => [PipelineFactoryInterface::class => PipelineFactory::class]
               ]
        ];
    }
}
