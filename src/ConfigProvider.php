<?php


namespace Bermuda\Pipeline;


use Psr\Container\ContainerInterface;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return ['dependencies' => [
                    'factories' => [
                        PipelineInterface::class => function(ContainerInterface $c)
                        {
                            return ($c->get(PipelineFactoryInterface::class))->make();
                        },

                        PipelineFactoryInterface::class => function()
                        {
                            return new PipelineFactory();
                        }
                    ]
               ]
        ];
    }
}
