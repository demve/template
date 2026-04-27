<?php

declare(strict_types=1);

namespace Demeve\Template;

interface ModifierInterface
{
    /**
     * Receive all items of a section as an associative array
     * (ComponentName => content) and return the combined output string.
     *
     * @param array<string, string> $sections
     */
    public function process(array $sections): string;
}
