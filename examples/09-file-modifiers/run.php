<?php

require __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;
use Demeve\Template\Modifier\CssFileModifier;
use Demeve\Template\Modifier\JsFileModifier;

$t = new Template(
    __DIR__ . '/components',
    __DIR__ . '/cache'
);

$publicDir = __DIR__ . '/public/assets';
$publicUrlPrefix = 'public/assets';

// Use the new modifiers, with read_file = false to return the URL string
$t->addModifier('css', new CssFileModifier([
    'output_dir' => $publicDir,
    'public_url_prefix' => $publicUrlPrefix,
    'read_file' => false
]));

$t->addModifier('js', new JsFileModifier([
    'output_dir' => $publicDir,
    'public_url_prefix' => $publicUrlPrefix,
    'read_file' => false
]));

echo $t->render('home');
