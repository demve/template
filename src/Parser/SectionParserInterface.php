<?php

declare(strict_types=1);

namespace Demeve\Template\Parser;

interface SectionParserInterface
{
    public function compile(string $content): string;
}
