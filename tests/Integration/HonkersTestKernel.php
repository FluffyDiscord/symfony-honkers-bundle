<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Integration;

use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use FluffyDiscord\HonkersBundle\Contract\CredentialsWriterInterface;
use FluffyDiscord\HonkersBundle\FluffyDiscordHonkersBundle;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\AlphaTool;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\CursorConsumingDataSource;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\RecordingCredentialsWriter;
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
     * @param array<string, mixed> $honkersConfig
     * @param ?class-string        $credentialsProviderClass
     */
    public function __construct(
        string                   $environment,
        bool                     $debug,
        private readonly array   $honkersConfig = [],
        private readonly ?string $credentialsProviderClass = null,
        private readonly bool    $withCredentialsWriter = false,
    ) {
        parent::__construct($environment, $debug);
    }

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
        $container->extension('fluffy_discord_honkers', $this->honkersConfig);

        $services = $container->services();

        if ($this->credentialsProviderClass !== null) {
            $services->set($this->credentialsProviderClass);
            $services->alias(CredentialsProviderInterface::class, $this->credentialsProviderClass);
        }

        if ($this->withCredentialsWriter) {
            $services->set(RecordingCredentialsWriter::class);
            $services->alias(CredentialsWriterInterface::class, RecordingCredentialsWriter::class);
        }

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
        $routes->import(__DIR__ . '/../../config/routes.php');
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
