<?php

declare(strict_types=1);

namespace Demeve\Template\Tests\Parser;

use Demeve\Template\Parser\OutputParser;
use PHPUnit\Framework\TestCase;

class OutputParserTest extends TestCase
{
    private OutputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new OutputParser();
    }

    public function test_compiles_php_block(): void
    {
        $output = $this->parser->compile('<dmv-php>$x = 1;</dmv-php>');
        $this->assertStringContainsString('<?php $x = 1; ?>', $output);
    }

    public function test_compiles_raw_echo(): void
    {
        $output = $this->parser->compile('<%%$foo%%>');
        $this->assertStringContainsString('<?php echo $foo; ?>', $output);
    }

    public function test_compiles_escaped_echo(): void
    {
        $output = $this->parser->compile('<%$bar%>');
        $this->assertStringContainsString('htmlspecialchars($bar', $output);
    }

    public function test_compiles_if_else_endif(): void
    {
        $output = $this->parser->compile('<dmv-if test="$x > 0">yes<dmv-else>no</dmv-if>');
        $this->assertStringContainsString('<?php if($x > 0): ?>', $output);
        $this->assertStringContainsString('<?php else: ?>', $output);
        $this->assertStringContainsString('<?php endif; ?>', $output);
    }

    public function test_compiles_elseif(): void
    {
        $output = $this->parser->compile('<dmv-if test="$x > 0">pos<dmv-elseif test="$x < 0">neg<dmv-else>zero</dmv-if>');
        $this->assertStringContainsString('<?php if($x > 0): ?>', $output);
        $this->assertStringContainsString('<?php elseif($x < 0): ?>', $output);
        $this->assertStringContainsString('<?php else: ?>', $output);
    }

    public function test_compiles_if_with_nested_function_call(): void
    {
        $output = $this->parser->compile('<dmv-if test="count($items) > 0">yes</dmv-if>');
        $this->assertStringContainsString('<?php if(count($items) > 0): ?>', $output);
    }

    public function test_compiles_foreach(): void
    {
        $output = $this->parser->compile('<dmv-foreach on="$items as $item"><li><%$item%></li></dmv-foreach>');
        $this->assertStringContainsString('<?php foreach($items as $item): ?>', $output);
        $this->assertStringContainsString('<?php endforeach; ?>', $output);
    }

    public function test_compiles_foreach_with_key(): void
    {
        $output = $this->parser->compile('<dmv-foreach on="$items as $i => $name"><li></li></dmv-foreach>');
        $this->assertStringContainsString('<?php foreach($items as $i => $name): ?>', $output);
    }

    public function test_compiles_for_endfor(): void
    {
        $output = $this->parser->compile('<dmv-for expr="$i = 0; $i < 5; $i++"><%$i%></dmv-for>');
        $this->assertStringContainsString('<?php for($i = 0; $i < 5; $i++): ?>', $output);
        $this->assertStringContainsString('<?php endfor; ?>', $output);
    }

    public function test_compiles_while_endwhile(): void
    {
        $output = $this->parser->compile('<dmv-while test="$n < 5"><%$n%><dmv-php>$n++;</dmv-php></dmv-while>');
        $this->assertStringContainsString('<?php while($n < 5): ?>', $output);
        $this->assertStringContainsString('<?php endwhile; ?>', $output);
    }

    public function test_compiles_render(): void
    {
        $output = $this->parser->compile('<dmv-render component="Widget" />');
        $this->assertStringContainsString("\$builder->render('Widget')", $output);
    }

    public function test_compiles_render_section_without_default(): void
    {
        $output = $this->parser->compile('<dmv-renderSection name="content" />');
        $this->assertStringContainsString("\$builder->renderSection('content')", $output);
        $this->assertStringNotContainsString("'content', ''", $output);
    }

    public function test_compiles_render_section_with_default(): void
    {
        $output = $this->parser->compile('<dmv-renderSection name="title" default="My App" />');
        $this->assertStringContainsString("\$builder->renderSection('title', 'My App')", $output);
    }

    public function test_compiles_render_section_block_without_modifier(): void
    {
        $output = $this->parser->compile('<dmv-renderSectionBlock name="style" />');
        $this->assertStringContainsString("\$builder->renderSectionBlock('style')", $output);
        $this->assertStringNotContainsString("'style', ''", $output);
    }

    public function test_compiles_render_section_block_with_modifier(): void
    {
        $output = $this->parser->compile('<dmv-renderSectionBlock name="style" modifier="css" />');
        $this->assertStringContainsString("\$builder->renderSectionBlock('style', 'css')", $output);
    }
}
