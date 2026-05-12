<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    path:  __DIR__ . '/components',
    cache: __DIR__ . '/cache'
);

$t->set('user',       ['name' => 'Diego', 'role' => 'admin']);
$t->set('frameworks', ['Blade', 'Twig', 'Smarty', 'Latte']);
$t->set('nothing',    []);
$t->set('count',      6);
$t->set('whileStart', 1);

// render() automatically calls load() if the component has not been loaded yet.
echo $t->render('Page');

$t->logErrores('js');
