<?php

namespace Bermuda\Http\Middleware;

use Psr\Container\ContainerInterface;
use function Bermuda\Config\conf;

final class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    public const string CONFIG_KEY_MIDDLEWARES = 'Bermuda\Http\Middleware\Pipeline:middlewares';
    public const string CONFIG_KEY_FALLBACK_HANDLER = 'Bermuda\Http\Middleware\Pipeline:fallbackHandler';

    /**
     * @inheritDoc
     */
    protected function getFactories(): array
    {
        return [PipelineInterface::class => static function (ContainerInterface $container): PipelineInterface {
            return $container->get(PipelineFactoryInterface::class)->createMiddlewarePipeline(
                ($conf = conf($container))->get(self::CONFIG_KEY_MIDDLEWARES, []),
                $conf->get(self::CONFIG_KEY_FALLBACK_HANDLER)
            );
        }];
    }

    protected function getInvokables(): array
    {
        return [PipelineFactoryInterface::class => PipelineFactory::class];
    }
}
