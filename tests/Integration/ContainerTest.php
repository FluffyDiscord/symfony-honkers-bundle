<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Integration;

use FluffyDiscord\Honkers\Ingest\CatalogIngestClient;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\Honkers\Schema\ArgumentsSchemaGenerator;
use FluffyDiscord\Honkers\Widget\WidgetSnippet;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\AlphaTool;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\CursorConsumingDataSource;
use PHPUnit\Framework\TestCase;

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
        self::assertInstanceOf(WidgetSnippet::class, $container->get(WidgetSnippet::class));
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
