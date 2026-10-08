<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Reporting;

use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class BackendReportGuardTest extends TestCase
{
    public function testAMissingBackendUrlIsListed(): void
    {
        $unconfiguredGuard = new BackendReportGuard(new NullLogger(), '', 'prod');
        $configuredGuard = new BackendReportGuard(new NullLogger(), 'https://honkers.example', 'prod');

        self::assertSame(['backend_url'], $unconfiguredGuard->getMissingBackendConfigurationKeys());
        self::assertSame([], $configuredGuard->getMissingBackendConfigurationKeys());
    }

    public function testMissingConfigurationIsRefusedSilently(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());
        $unconfiguredGuard = new BackendReportGuard($logger, '', 'prod');

        self::assertFalse($unconfiguredGuard->canReport('chat link visit'));
        self::assertFalse($unconfiguredGuard->canReport('chat link visit'));
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
        $backendReportGuard = new BackendReportGuard($logger, 'http://honkers.example', 'prod');

        self::assertFalse($backendReportGuard->canReport('chat link visit'));
    }

    public function testAnHttpBackendIsAllowedInDev(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());
        $backendReportGuard = new BackendReportGuard($logger, 'http://honkers.example', 'dev');

        self::assertTrue($backendReportGuard->canReport('chat link visit'));
    }

    public function testAnHttpsBackendIsAllowed(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());
        $backendReportGuard = new BackendReportGuard($logger, 'https://honkers.example', 'prod');

        self::assertTrue($backendReportGuard->canReport('chat link visit'));
    }
}
