<?php

declare(strict_types=1);

namespace Demeve\Template;

interface FileModifierInterface
{
    /**
     * Receive all items of a section as an associative array of file paths
     * (ComponentName => absolute path to .cache.php file) and return the
     * combined output string — without reading the files unless necessary.
     *
     * @param array<string, string> $files
     */
    public function processFiles(array $files): string;
}
