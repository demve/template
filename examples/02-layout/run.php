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

// Rendering PageHome triggers:
//   1. load('PageHome')  → registers its sections, then auto-loads Layout
//   2. load('Layout')    → compiles its output section
//   3. render()          → includes Layout's output cache (not PageHome's)
//
// PageHome's sections (title, content, style) are available inside Layout
// via renderSection() and renderSectionBlock().
$t->render('PageHome');

$t->consoleErrors();
