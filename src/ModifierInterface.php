<?php

declare(strict_types=1);

namespace Demeve\Template;

interface ModifierInterface
{
    public function process(string $content): string;
}
