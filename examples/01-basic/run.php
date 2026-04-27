<?php

/**
 * Basic usage: load a component and render its output section.
 *
 * Run: php examples/01-basic/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);

// Template variables are available inside component files via $builder->get()
// and via the <!--print::key--> comment directive.
$t->set('name', 'World');
$t->set('year', date('Y'));

// render() calls load() automatically — you rarely need to call load() directly.
$t->render('Greeting');

// Errors (missing files, open sections, etc.) are collected silently and can
// be flushed to the browser console with consoleErrors().
$t->consoleErrors();
