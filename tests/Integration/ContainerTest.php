<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Integration;

use FluffyDiscord\Honkers\Ingest\CatalogIngestClient;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\Honkers\Schema\ArgumentsSchemaGenerator;
use FluffyDiscord\Honkers\Telemetry\TelemetryClient;
use FluffyDiscord\Honkers\Widget\WidgetSnippet;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use FluffyDiscord\HonkersBundle\Controller\PairController;
use FluffyDiscord\HonkersBundle\Credentials\EnvCredentialsProvider;
use FluffyDiscord\HonkersBundle\EventListener\LinkVisitBeacon;
use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\AlphaTool;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\CursorConsumingDataSource;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\FixedCredentialsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ContainerTest extends TestCase
{
    public function testTheBundleWiresTaggedToolsIntoTheRegistry(): void
    {
        $kernel = new HonkersTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $registry = $container->get(ToolRegistry::class);

        $tool = $registry->get('alpha_tool');
        self::assertInstanceOf(AlphaTool::class, $tool);

        $tools = iterator_to_array($registry->all(), false);
        self::assertCount(1, $tools);
        self::assertInstanceOf(AlphaTool::class, $tools[0]);
    }

    public function testTheBundleWiresTheSchemaGenerator(): void
    {
        $kernel = new HonkersTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $generator = $container->get(ArgumentsSchemaGenerator::class);
        $tool = $container->get(ToolRegistry::class)->get('alpha_tool');

        $schema = $generator->generate($tool->getArgumentsClass());

        self::assertSame('object', $schema['type']);
    }

    public function testTheOutboundClientAndWidgetBuilderAreWired(): void
    {
        $kernel = new HonkersTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        self::assertInstanceOf(CatalogIngestClient::class, $container->get(CatalogIngestClient::class));
        self::assertInstanceOf(TelemetryClient::class, $container->get(TelemetryClient::class));
        self::assertInstanceOf(WidgetSnippet::class, $container->get(WidgetSnippet::class));
        self::assertInstanceOf(EnvCredentialsProvider::class, $container->get(CredentialsProviderInterface::class));
        self::assertInstanceOf(LinkVisitBeacon::class, $container->get(LinkVisitBeacon::class));
        self::assertInstanceOf(PairController::class, $container->get(PairController::class));
    }

    public function testTheCredentialsProviderAliasCanBeOverridden(): void
    {
        $kernel = new HonkersTestKernel('test', true, credentialsProviderClass: FixedCredentialsProvider::class);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        self::assertInstanceOf(FixedCredentialsProvider::class, $container->get(CredentialsProviderInterface::class));
    }

    public function testTheWidgetIsDeferredByDefault(): void
    {
        $kernel = new HonkersTestKernel('test', true);
        $kernel->boot();

        self::assertTrue($kernel->getContainer()->getParameter('fluffydiscord_honkers.widget.defer'));
    }

    public function testTheWidgetDeferCanBeTurnedOff(): void
    {
        $kernel = new HonkersTestKernel('test', true, ['widget' => ['defer' => false]]);
        $kernel->boot();

        self::assertFalse($kernel->getContainer()->getParameter('fluffydiscord_honkers.widget.defer'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideRemovedConfiguration(): iterable
    {
        yield 'api_secret' => [['api_secret' => 'top-secret']];
        yield 'ingest_secret' => [['ingest_secret' => 'ingest-secret']];
        yield 'widget.site_key' => [['widget' => ['site_key' => 'site-a']]];
    }

    /**
     * @param array<string, mixed> $honkersConfig
     */
    #[DataProvider('provideRemovedConfiguration')]
    public function testRemovedConfigurationIsRejected(array $honkersConfig): void
    {
        $kernel = new HonkersTestKernel('test', true, $honkersConfig);

        $this->expectException(InvalidConfigurationException::class);

        $kernel->boot();
    }

    public function testTheBackendReportGuardIsPublic(): void
    {
        $kernel = new HonkersTestKernel('test', true);
        $kernel->boot();

        $backendReportGuard = $kernel->getContainer()->get(BackendReportGuard::class);

        self::assertInstanceOf(BackendReportGuard::class, $backendReportGuard);
    }

    public function testADataSourceCanAutowireTheSdkCursorAndTextUtilities(): void
    {
        $kernel = new HonkersTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $dataSource = $container->get(DataSourceRegistry::class)->get('cursor_consuming');

        self::assertInstanceOf(CursorConsumingDataSource::class, $dataSource);
    }

    protected function tearDown(): void
    {
        $cacheDir = sys_get_temp_dir() . '/honkers_bundle_test';
        if (is_dir($cacheDir)) {
            exec('rm -rf ' . escapeshellarg($cacheDir));
        }
    }
}
