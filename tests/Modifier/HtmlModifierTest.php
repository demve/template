<?php

declare(strict_types=1);

namespace Demeve\Template\Tests\Modifier;

use Demeve\Template\Modifier\HtmlModifier;
use PHPUnit\Framework\TestCase;

class HtmlModifierTest extends TestCase
{
    private HtmlModifier $modifier;

    protected function setUp(): void
    {
        $this->modifier = new HtmlModifier();
    }

    public function test_empty_sections_returns_empty_string(): void
    {
        $this->assertSame('', $this->modifier->process([]));
    }

    public function test_prepends_html_comment_with_component_name(): void
    {
        $output = $this->modifier->process(['Widget' => '<p>hello</p>']);
        $this->assertStringContainsString('<!-- Widget -->', $output);
        $this->assertStringContainsString('<p>hello</p>', $output);
    }

    public function test_comment_appears_before_content(): void
    {
        $output = $this->modifier->process(['Widget' => '<p>hello</p>']);
        $this->assertLessThan(
            strpos($output, '<p>hello</p>'),
            strpos($output, '<!-- Widget -->')
        );
    }

    public function test_multiple_sections_each_get_own_comment(): void
    {
        $output = $this->modifier->process([
            'Header' => '<header>top</header>',
            'Footer' => '<footer>bottom</footer>',
        ]);
        $this->assertStringContainsString('<!-- Header -->', $output);
        $this->assertStringContainsString('<!-- Footer -->', $output);
        $this->assertStringContainsString('<header>top</header>', $output);
        $this->assertStringContainsString('<footer>bottom</footer>', $output);
    }

    public function test_sections_appear_in_original_order(): void
    {
        $output = $this->modifier->process(['A' => 'first', 'B' => 'second']);
        $this->assertLessThan(strpos($output, '<!-- B -->'), strpos($output, '<!-- A -->'));
    }
}
