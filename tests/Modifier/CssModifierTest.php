<?php

declare(strict_types=1);

namespace Demeve\Template\Tests\Modifier;

use Demeve\Template\Modifier\CssModifier;
use PHPUnit\Framework\TestCase;

class CssModifierTest extends TestCase
{
    private CssModifier $modifier;

    protected function setUp(): void
    {
        $this->modifier = new CssModifier();
    }

    public function test_empty_sections_returns_empty_string(): void
    {
        $this->assertSame('', $this->modifier->process([]));
    }

    public function test_prepends_block_comment_with_component_name(): void
    {
        $output = $this->modifier->process(['Button' => '.btn { color: red; }']);
        $this->assertStringContainsString('/* Button */', $output);
    }

    public function test_comment_appears_before_content(): void
    {
        $output = $this->modifier->process(['Button' => '.btn{color:red}']);
        $this->assertLessThan(strpos($output, '.btn'), strpos($output, '/* Button */'));
    }

    public function test_collapses_whitespace(): void
    {
        $output = $this->modifier->process(['A' => ".foo {\n  color:   red;\n}"]);
        $this->assertStringContainsString('.foo{color:red}', $output);
    }

    public function test_removes_existing_css_comments(): void
    {
        $output = $this->modifier->process(['A' => '/* old comment */ .foo { color: red; }']);
        $this->assertStringNotContainsString('old comment', $output);
    }

    public function test_component_comment_survives_after_minification(): void
    {
        // The component comment is added AFTER minification, so it must not be stripped.
        $output = $this->modifier->process(['Widget' => '/* will be stripped */ .x{color:red}']);
        $this->assertStringContainsString('/* Widget */', $output);
        $this->assertStringNotContainsString('will be stripped', $output);
    }

    public function test_removes_trailing_semicolons_before_closing_brace(): void
    {
        $output = $this->modifier->process(['A' => '.foo { color: red; }']);
        $this->assertStringContainsString('.foo{color:red}', $output);
    }

    public function test_multiple_sections_each_get_own_comment(): void
    {
        $output = $this->modifier->process([
            'Page'   => '.page { margin: 0; }',
            'Button' => '.btn  { padding: 0; }',
        ]);
        $this->assertStringContainsString('/* Page */', $output);
        $this->assertStringContainsString('/* Button */', $output);
    }
}
