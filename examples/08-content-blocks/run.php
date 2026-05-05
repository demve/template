<?php

/**
 * CMS Content Blocks: los componentes a renderizar se conocen solo en runtime.
 * Se pasa un array desordenado/aleatorio de bloques como data a render().
 *
 * Cada bloque tiene su propio section::style. Con el mecanismo de deferred inject,
 * renderSectionBlock('style') en page.html emite un placeholder al ejecutarse,
 * y solo después de que todos los bloques son cargados/renderizados, inject()
 * reemplaza el placeholder con los estilos acumulados de todos los bloques.
 *
 * Run: php examples/08-content-blocks/run.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Demeve\Template\Template;

$t = new Template(
    componentsDir: __DIR__ . '/components',
    cacheDir:      __DIR__ . '/cache'
);

// Simula bloques de CMS: tipo y datos variables, orden aleatorio cada vez
$pool = [
    (object)[
        'componente' => 'BlocksText',
        'data'       => ['texto' => 'Bienvenido a la demo de content blocks. Cada bloque es un componente independiente con su propio estilo.'],
    ],
    (object)[
        'componente' => 'BlocksQuote',
        'data'       => ['cita' => 'El buen diseño es tan poco diseño como sea posible.', 'autor' => 'Dieter Rams'],
    ],
    (object)[
        'componente' => 'BlocksText',
        'data'       => ['texto' => 'Los estilos de cada bloque se inyectan en el <head> aunque el componente se cargue después del renderSectionBlock.'],
    ],
    (object)[
        'componente' => 'BlocksImage',
        'data'       => ['src' => 'https://picsum.photos/seed/blocks/800/300', 'alt' => 'placeholder', 'caption' => 'Imagen de ejemplo (picsum.photos)'],
    ],
    (object)[
        'componente' => 'BlocksQuote',
        'data'       => ['cita' => 'Simplicity is the ultimate sophistication.', 'autor' => 'Leonardo da Vinci'],
    ],
    (object)[
        'componente' => 'BlocksText',
        'data'       => ['texto' => 'Recarga la página — el orden de los bloques cambia cada vez, pero los estilos siempre están presentes.'],
    ],
];

// Orden aleatorio en cada request para demostrar que el timing no importa
shuffle($pool);

// Subset aleatorio: entre 3 y 6 bloques
$bloques = array_slice($pool, 0, rand(3, count($pool)));

$t->render('Page', [
    'titulo'  => '08 – Content Blocks',
    'bloques' => $bloques,
]);
