<?php


namespace Bermuda\Pipeline;


final class ConfigProvider
{
    public function __invoke(): array
    {
        return ['dependencies' => ['factories' => [PipelineInterface::class => PipelineFactory::class}]]];
    }
}
