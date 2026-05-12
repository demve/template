<?php

/**
 * Auto-load: render() triggers load() automatically.
 * No manual load() calls needed for Card or Badge.
 *
 * Run: php examples/07-auto-load/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    path:  __DIR__ . '/components',
    cache: __DIR__ . '/cache'
);

$t->set('label', 'new');
$t->set('title', 'Auto-loaded card');
$t->set('body',  'Card and Badge were loaded automatically — no explicit load() needed.');

// Only load Page. Card and Badge are discovered from $this->render() in the output
// section and loaded automatically.
echo $t->render('Page');

$t->logErrores('js');
