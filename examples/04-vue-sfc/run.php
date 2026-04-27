<?php

/**
 * Vue-SFC style: two self-contained view components (Login, Dashboard) loaded
 * by a main App component. Each view contributes style, template, and script
 * sections that the layout collects and places in the right spots.
 *
 * Run: php examples/04-vue-sfc/run.php
 * Or open in a browser via a local web server.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);

$t->render('App');
$t->consoleErrors();
