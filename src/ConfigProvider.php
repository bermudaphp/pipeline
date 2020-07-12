<?php


namespace Bermuda\Pipeline;


final class ConfigProvider
{
    public function __invoke(): array
    {
        return ['dependencies' => [
                    'factories' => [PipelineInterface::class => PipelineFactoryInterface::class}], 
                    'autowires' => [PipelineFactoryInterface::class => PipelineFactory::class]
               ]
        ];
    }
}
