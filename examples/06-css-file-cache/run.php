<?php

/**
 * CSS file cache example: modifier minifies collected CSS once and saves it to
 * public/styles.css. On subsequent runs it skips minification when the file is
 * newer than all section cache files.
 *
 * Run: php examples/06-css-file-cache/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/CssFileModifier.php';

use Demeve\Template\Template;

$t = new Template(
    path:  __DIR__ . '/components',
    cache: __DIR__ . '/cache'
);

$t->addModifier('css-file', new CssFileModifier(
    outputFile: __DIR__ . '/cache/styles.css',
    publicUrl:  'cache/styles.css',
));

$t->load('Page');

$html = (string) $t->render('Page');

echo $html;
$t->logErrores('js');
