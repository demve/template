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
    path:  __DIR__ . '/components',
    cache: __DIR__ . '/cache'
);

// Register modifiers by key. Component files reference them by name —
// no PHP class instantiation inside .html files.
$t->addModifier('css', new CssMinifier());

// render() automatically calls load() if the component has not been loaded yet.
echo $t->render('Page');

$t->logErrores('js');
