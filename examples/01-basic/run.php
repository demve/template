<?php

/**
 * Basic usage: load a component and render its output section.
 *
 * Run: php examples/01-basic/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    path:  __DIR__ . '/components',
    cache: __DIR__ . '/cache'
);

// Template variables are available inside component files via $builder->get()
// and via the <!--print::key--> comment directive.
$t->set('name', 'World');
$t->set('year', date('Y')-1);

//render() automatically calls load() if the component has not been loaded yet.
echo $t->render('Greeting');

// Errors (missing files, open sections, etc.) are collected silently and can
// be flushed to the browser console with logErrores().
$t->logErrores('js');
