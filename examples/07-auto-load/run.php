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
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);

$t->set('label', 'new');
$t->set('title', 'Auto-loaded card');
$t->set('body',  'Card and Badge were loaded automatically — no explicit load() needed.');

// Only load Page. Card and Badge are discovered from <dmv-render> in the output
// section and loaded automatically. render() also auto-loads if called directly.
$t->render('Page');
