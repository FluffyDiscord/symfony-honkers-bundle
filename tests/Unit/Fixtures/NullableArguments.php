<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class NullableArguments
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $query = '',
        public readonly ?string $note = null,
        #[Assert\Choice(choices: ['relevance', 'price'])]
        public readonly string $sort = 'relevance',
    ) {
    }
}
