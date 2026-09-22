<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Integration;

use FluffyDiscord\HonkersBundle\FluffyDiscordHonkersBundle;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\AlphaTool;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\CursorConsumingDataSource;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class HonkersTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new FluffyDiscordHonkersBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('fluffy_discord_honkers', [
            'api_secret' => 'top-secret',
        ]);

        $services = $container->services();

        $services
            ->set(AlphaTool::class)
            ->autoconfigure()
            ->autowire();

        $services
            ->set(CursorConsumingDataSource::class)
            ->autoconfigure()
            ->autowire();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/honkers_bundle_test/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/honkers_bundle_test/log';
    }
}
