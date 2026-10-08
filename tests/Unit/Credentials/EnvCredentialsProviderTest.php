<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Credentials;

use FluffyDiscord\HonkersBundle\Credentials\EnvCredentialsProvider;
use PHPUnit\Framework\TestCase;

class EnvCredentialsProviderTest extends TestCase
{
    public function testTheConfiguredApiSecretIsValid(): void
    {
        $provider = new EnvCredentialsProvider('top-secret', 'ingest-secret', 'site-a');

        self::assertTrue($provider->isApiSecretValid('top-secret'));
        self::assertFalse($provider->isApiSecretValid('wrong-secret'));
        self::assertFalse($provider->isApiSecretValid(''));
    }

    public function testAnEmptyApiSecretIsNeverValid(): void
    {
        $provider = new EnvCredentialsProvider('', 'ingest-secret', 'site-a');

        self::assertFalse($provider->isApiSecretValid(''));
        self::assertFalse($provider->isApiSecretValid('anything'));
    }

    public function testTheCurrentSiteCarriesTheSiteKeyAndIngestSecret(): void
    {
        $provider = new EnvCredentialsProvider('top-secret', 'ingest-secret', 'site-a');

        $site = $provider->findCurrentSite();

        self::assertNotNull($site);
        self::assertSame('site-a', $site->siteKey);
        self::assertSame('ingest-secret', $site->ingestSecret);
    }

    public function testThereIsNoCurrentSiteWithoutASiteKey(): void
    {
        $provider = new EnvCredentialsProvider('top-secret', 'ingest-secret', '');

        self::assertNull($provider->findCurrentSite());
    }

    public function testTheCurrentSiteWithoutAnIngestSecretStillCarriesTheSiteKey(): void
    {
        $provider = new EnvCredentialsProvider('top-secret', '', 'site-a');

        $site = $provider->findCurrentSite();

        self::assertNotNull($site);
        self::assertSame('site-a', $site->siteKey);
        self::assertFalse($site->hasIngestSecret());
    }

    public function testAGivenSiteKeyGetsTheEnvIngestSecret(): void
    {
        $provider = new EnvCredentialsProvider('top-secret', 'ingest-secret', 'site-a');

        $site = $provider->findSite('site-b');

        self::assertSame('site-b', $site->siteKey);
        self::assertSame('ingest-secret', $site->ingestSecret);
    }
}
