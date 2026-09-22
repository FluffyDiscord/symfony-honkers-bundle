<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures;

use FluffyDiscord\Honkers\Contract\ChatbotDataSourceInterface;
use FluffyDiscord\Honkers\Cursor\CursorCodec;
use FluffyDiscord\Honkers\DTO\DocumentPage;
use FluffyDiscord\Honkers\DTO\SourceDefinition;
use FluffyDiscord\Honkers\DTO\SourceQuery;
use FluffyDiscord\Honkers\Text\HtmlToText;

class CursorConsumingDataSource implements ChatbotDataSourceInterface
{
    public function __construct(
        private readonly CursorCodec $cursorCodec,
        private readonly HtmlToText  $htmlToText,
    ) {
    }

    public function getDefinition(): SourceDefinition
    {
        return new SourceDefinition('cursor_consuming', 'fixture.cursor_consuming');
    }

    public function getDocuments(SourceQuery $query): DocumentPage
    {
        return new DocumentPage([]);
    }
}
