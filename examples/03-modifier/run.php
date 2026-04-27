<?php

/**
 * Modifier example: collect CSS from all components and minify it in one pass.
 *
 * Run: php examples/03-modifier/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/CssMinifier.php';

use Demeve\Template\Template;

$t = new Template(
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);

// Capture the full output so we can show it cleanly in the terminal.
$html = (string) $t->render('Page', [], return: true);

echo $html;
$t->consoleErrors();
