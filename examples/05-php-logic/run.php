<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);

$t->set('user',       ['name' => 'Diego', 'role' => 'admin']);
$t->set('frameworks', ['Blade', 'Twig', 'Smarty', 'Latte']);
$t->set('nothing',    []);
$t->set('count',      6);
$t->set('whileStart', 1);

$t->load('Page');
$t->render('Page');
$t->consoleErrors();
