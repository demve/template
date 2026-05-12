<?php

/**
 * Layout inheritance: a page component extends a layout that wraps it.
 *
 * Run: php examples/02-layout/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    path:  __DIR__ . '/components',
    cache: __DIR__ . '/cache'
);

// render() automatically calls load() if the component has not been loaded yet.
echo $t->render('PageHome');

$t->logErrores('js');
