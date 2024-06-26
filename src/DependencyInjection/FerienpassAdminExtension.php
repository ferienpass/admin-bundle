<?php

declare(strict_types=1);

/*
 * This file is part of the Ferienpass package.
 *
 * (c) Richard Henkenjohann <richard@ferienpass.online>
 *
 * For more information visit the project website <https://ferienpass.online>
 * or the documentation under <https://docs.ferienpass.online>.
 */

namespace Ferienpass\AdminBundle\DependencyInjection;

use DoctrineExtensions\Query\Mysql\DateFormat;
use DoctrineExtensions\Query\Mysql\TimestampDiff;
use Ferienpass\AdminBundle\State\SystemStatus;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonSearch;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class FerienpassAdminExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $container->setParameter('ferienpass_admin.privacy_consent_text', $config['privacy_consent_text'] ?? null);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependTwigBundle($container);
        $this->prependDoctrineBundle($container);

        $container->prependExtensionConfig('twig_component', [
            'defaults' => [
                'Ferienpass\AdminBundle\Components\\' => [
                    'template_directory' => '@FerienpassAdmin/components',
                    'name_prefix' => 'Admin',
                ],
            ],
        ]);

        if ($this->isAssetMapperAvailable($container)) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__.'/../../assets/dist' => '@ferienpass/ux-admin',
                    ],
                ],
            ]);
        }
    }

    private function prependTwigBundle(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('twig', [
            'form_themes' => [
                '@FerienpassAdmin/form/custom_types.html.twig',
                '@FerienpassAdmin/form/form_types.html.twig',
            ],
            'globals' => [
                'systemStatus' => '@'.SystemStatus::class,
            ],
        ]);
    }

    private function prependDoctrineBundle(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'dql' => [
                    'string_functions' => [
                        'DATE_FORMAT' => DateFormat::class,
                        'TIMESTAMPDIFF' => TimestampDiff::class,
                        'JSON_SEARCH' => JsonSearch::class,
                    ],
                ],
            ],
        ]);
    }

    private function isAssetMapperAvailable(ContainerBuilder $container): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        // check that FrameworkBundle 6.3 or higher is installed
        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        if (!isset($bundlesMetadata['FrameworkBundle'])) {
            return false;
        }

        return is_file($bundlesMetadata['FrameworkBundle']['path'].'/Resources/config/asset_mapper.php');
    }
}
