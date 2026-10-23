<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiPresentationTest extends TestCase
{
    public function test_password_input_starts_masked_and_has_one_accessible_toggle(): void
    {
        $html = Blade::render('<x-text-input id="audit-password" type="password" name="password" />');
        $this->assertStringContainsString('type="password"', $html);
        $this->assertSame(1, substr_count($html, '<button'));
        $this->assertStringContainsString('Show password', $html);
        $this->assertStringContainsString('Hide password', $html);
    }

    public function test_icon_has_an_accessible_name_when_used_without_text(): void
    {
        $html = Blade::render('<x-ui-icon name="users" label="People and teams" />');
        $this->assertStringContainsString('aria-label="People and teams"', $html);
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringNotContainsString('aria-hidden="true"', $html);
    }
}
