<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures;

use FluffyDiscord\Honkers\Contract\ChatbotDataSourceInterface;
use FluffyDiscord\Honkers\DTO\DocumentPage;
use FluffyDiscord\Honkers\DTO\SourceDefinition;
use FluffyDiscord\Honkers\DTO\SourceQuery;

class PlainDataSource implements ChatbotDataSourceInterface
{
    /**
     * @param ?list<string> $locales
     */
    public function __construct(
        private readonly string $name = 'plain',
        private readonly ?array $locales = null,
    ) {
    }

    public function getDefinition(): SourceDefinition
    {
        return new SourceDefinition($this->name, 'fixture.' . $this->name, $this->locales);
    }

    public function getDocuments(SourceQuery $query): DocumentPage
    {
        return new DocumentPage([]);
    }
}
