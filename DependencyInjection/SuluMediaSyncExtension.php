<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SuluMediaSyncExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sulu_media_sync.enabled', $config['enabled']);
        $container->setParameter('sulu_media_sync.source_locale', $config['source_locale']);
        $container->setParameter('sulu_media_sync.template_directories', $config['template_directories']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('sulu_admin')) {
            $container->prependExtensionConfig('sulu_admin', [
                'forms' => [
                    'directories' => [__DIR__ . '/../Resources/config/forms'],
                ],
                'resources' => [
                    'media_sync_settings' => [
                        'routes' => [
                            'detail' => 'sulu_media_sync.get_settings',
                        ],
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'translator' => [
                    'paths' => [__DIR__ . '/../Resources/translations'],
                ],
            ]);
        }

        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SuluMediaSyncBundle' => [
                        'type' => 'xml',
                        'dir' => __DIR__ . '/../Resources/config/doctrine',
                        'prefix' => 'Ahmed\SuluMediaSyncBundle\Entity',
                        'alias' => 'SuluMediaSync',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }
}
