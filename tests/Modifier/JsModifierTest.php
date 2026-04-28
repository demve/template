<?php

declare(strict_types=1);

namespace Demeve\Template\Tests\Modifier;

use Demeve\Template\Modifier\JsModifier;
use PHPUnit\Framework\TestCase;

class JsModifierTest extends TestCase
{
    private JsModifier $modifier;

    protected function setUp(): void
    {
        $this->modifier = new JsModifier();
    }

    public function test_empty_sections_returns_empty_string(): void
    {
        $this->assertSame('', $this->modifier->process([]));
    }

    public function test_prepends_block_comment_with_component_name(): void
    {
        $output = $this->modifier->process(['App' => 'var x = 1;']);
        $this->assertStringContainsString('/* App */', $output);
    }

    public function test_comment_appears_before_content(): void
    {
        $output = $this->modifier->process(['App' => 'var x = 1;']);
        $this->assertLessThan(strpos($output, 'var x'), strpos($output, '/* App */'));
    }

    public function test_removes_block_comments(): void
    {
        $output = $this->modifier->process(['A' => '/* old comment */ var x = 1;']);
        $this->assertStringNotContainsString('old comment', $output);
    }

    public function test_removes_line_comments(): void
    {
        $output = $this->modifier->process(['A' => "var x = 1; // inline comment\nvar y = 2;"]);
        $this->assertStringNotContainsString('inline comment', $output);
        $this->assertStringContainsString('var y', $output);
    }

    public function test_component_comment_survives_after_minification(): void
    {
        // The component comment is added AFTER minification, so it must not be stripped.
        $output = $this->modifier->process(['Router' => '/* will be stripped */ var x = 1;']);
        $this->assertStringContainsString('/* Router */', $output);
        $this->assertStringNotContainsString('will be stripped', $output);
    }

    public function test_collapses_horizontal_whitespace(): void
    {
        $output = $this->modifier->process(['A' => "var  x  =  1;"]);
        $this->assertStringContainsString('var x=1;', $output);
    }

    public function test_preserves_newlines_for_statement_termination(): void
    {
        // Newlines must not be removed entirely — they act as statement separators.
        $output = $this->modifier->process(['A' => "var x = 1\nvar y = 2"]);
        $this->assertStringContainsString("\n", $output);
    }

    public function test_multiple_sections_each_get_own_comment(): void
    {
        $output = $this->modifier->process([
            'Router' => 'function navigate() {}',
            'Login'  => 'function login() {}',
        ]);
        $this->assertStringContainsString('/* Router */', $output);
        $this->assertStringContainsString('/* Login */', $output);
    }
}
