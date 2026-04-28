<?php

declare(strict_types=1);

namespace Demeve\Template\Tests\Parser;

use Demeve\Template\Parser\ContentParser;
use PHPUnit\Framework\TestCase;

class ContentParserTest extends TestCase
{
    private ContentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ContentParser();
    }

    public function test_replaces_dmv_template_tags(): void
    {
        $input  = '<dmv-template>foo</dmv-template>';
        $output = $this->parser->parse($input, 'MyComponent');

        $this->assertStringContainsString("<script type='text/template' id='template-MyComponent'", $output);
        $this->assertStringContainsString('</script>', $output);
    }

    public function test_replaces_self_placeholder(): void
    {
        $output = $this->parser->parse('id="__self"', 'MyComponent');
        $this->assertStringContainsString('id="MyComponent"', $output);
    }

    public function test_removes_triple_dash_comments(): void
    {
        $output = $this->parser->parse('before<!--- dev note --->after', 'C');
        $this->assertSame('beforeafter', $output);
    }

    public function test_converts_single_arg_comment_directive(): void
    {
        $output = $this->parser->parse('<!--load::MyWidget-->', 'C');
        $this->assertStringContainsString('$builder->load("MyWidget")', $output);
    }

    public function test_converts_two_arg_comment_directive(): void
    {
        $output = $this->parser->parse('<!--switch::key|Yes|No-->', 'C');
        $this->assertStringContainsString('$builder->switch("key", "Yes", "No")', $output);
    }

    public function test_converts_js_block_comment_directive(): void
    {
        $output = $this->parser->parse('/*print::title*/', 'C');
        $this->assertStringContainsString('$builder->print("title")', $output);
    }
}
