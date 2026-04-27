<?php

/**
 * Layout inheritance: a page component extends a layout that wraps it.
 *
 * Run: php examples/02-layout/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);

// load() registers PageHome's sections and auto-loads Layout (via <!--extends::Layout-->).
// render() then includes Layout's output cache — it never auto-loads.
$t->load('PageHome');
$t->render('PageHome');

$t->consoleErrors();
