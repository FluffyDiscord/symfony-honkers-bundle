<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Reporting;

use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class BackendReportGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function provideMissingConfiguration(): iterable
    {
        yield 'all missing' => ['', '', '', ['backend_url', 'ingest_secret', 'widget.site_key']];
        yield 'backend url missing' => ['', 'ingest-secret', 'site-a', ['backend_url']];
        yield 'ingest secret missing' => ['https://honkers.example', '', 'site-a', ['ingest_secret']];
        yield 'site key missing' => ['https://honkers.example', 'ingest-secret', '', ['widget.site_key']];
        yield 'fully configured' => ['https://honkers.example', 'ingest-secret', 'site-a', []];
    }

    /**
     * @param list<string> $expectedMissingKeys
     */
    #[DataProvider('provideMissingConfiguration')]
    public function testMissingConfigurationKeysAreListed(string $backendUrl, string $ingestSecret, string $siteKey, array $expectedMissingKeys): void
    {
        $backendReportGuard = new BackendReportGuard(new NullLogger(), $backendUrl, $ingestSecret, 'prod');

        self::assertSame($expectedMissingKeys, $backendReportGuard->getMissingConfigurationKeys($siteKey));
    }

    public function testMissingBackendConfigurationKeysIgnoreTheSiteKey(): void
    {
        $backendReportGuard = new BackendReportGuard(new NullLogger(), '', 'ingest-secret', 'prod');

        self::assertSame(['backend_url'], $backendReportGuard->getMissingBackendConfigurationKeys());
    }

    public function testMissingConfigurationIsRefusedSilently(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());
        $unconfiguredGuard = new BackendReportGuard($logger, '', '', 'prod');
        $configuredGuard = new BackendReportGuard($logger, 'http://honkers.example', 'ingest-secret', 'prod');

        self::assertFalse($unconfiguredGuard->canReport('site-a', 'chat link visit'));
        self::assertFalse($unconfiguredGuard->canReport('site-a', 'chat link visit'));
        self::assertFalse($configuredGuard->canReport('', 'chat link visit'));
    }

    public function testAnHttpBackendIsRefusedAndLoggedOutsideDev(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Chatbot: refusing to report to a non-https backend URL.', [
                'feature' => 'chat link visit',
                'backendUrl' => 'http://honkers.example',
                'environment' => 'prod',
            ]);
        $backendReportGuard = new BackendReportGuard($logger, 'http://honkers.example', 'ingest-secret', 'prod');

        self::assertFalse($backendReportGuard->canReport('site-a', 'chat link visit'));
    }

    public function testAnHttpBackendIsAllowedInDev(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());
        $backendReportGuard = new BackendReportGuard($logger, 'http://honkers.example', 'ingest-secret', 'dev');

        self::assertTrue($backendReportGuard->canReport('site-a', 'chat link visit'));
    }

    public function testAnHttpsBackendIsAllowed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());
        $backendReportGuard = new BackendReportGuard($logger, 'https://honkers.example', 'ingest-secret', 'prod');

        self::assertTrue($backendReportGuard->canReport('site-a', 'chat link visit'));
    }
}
