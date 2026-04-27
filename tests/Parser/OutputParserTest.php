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
        $output = $this->parser->compile('@php $x = 1; @endphp');
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

    public function test_compiles_at_echo(): void
    {
        $output = $this->parser->compile('@echo($baz)');
        $this->assertStringContainsString('htmlspecialchars($baz', $output);
    }

    public function test_compiles_render_bare(): void
    {
        $output = $this->parser->compile("@render('Widget')");
        $this->assertStringContainsString("\$builder->subrender('Widget')", $output);
    }

    public function test_compiles_render_comment_form(): void
    {
        $output = $this->parser->compile("<!--@render('Widget')-->");
        $this->assertStringContainsString("\$builder->subrender('Widget')", $output);
    }

    public function test_compiles_render_section(): void
    {
        $output = $this->parser->compile("@renderSection('content')");
        $this->assertStringContainsString("\$builder->renderSection('content')", $output);
    }

    public function test_compiles_render_section_with_default(): void
    {
        $output = $this->parser->compile("@renderSection('title', 'My App')");
        $this->assertStringContainsString("\$builder->renderSection('title', 'My App')", $output);
    }

    public function test_compiles_render_section_block_bare(): void
    {
        $output = $this->parser->compile("@renderSectionBlock('style')");
        $this->assertStringContainsString("\$builder->renderSectionBlock('style')", $output);
    }

    public function test_compiles_render_section_block_comment_form(): void
    {
        $output = $this->parser->compile("<!--@renderSectionBlock('style')-->");
        $this->assertStringContainsString("\$builder->renderSectionBlock('style')", $output);
    }

    public function test_compiles_foreach(): void
    {
        $output = $this->parser->compile('@foreach($items as $item) { <li>item</li> }@endforeach');
        $this->assertStringContainsString('<?php foreach($items as $item): ?>', $output);
        $this->assertStringContainsString('<?php endforeach; ?>', $output);
    }
}
