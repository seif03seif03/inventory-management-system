<?php

namespace Tests\Feature;

use Tests\TestCase;

class RtlSidebarTest extends TestCase
{
    public function test_rtl_sidebar_collapses_toward_the_right_edge(): void
    {
        $css = file_get_contents(public_path('css/app.css'));

        $this->assertStringContainsString('[dir="rtl"] .app-shell.sidebar-collapsed .sidebar', $css);
        $this->assertStringContainsString('transform: translateX(100%);', $css);
        $this->assertStringContainsString('[dir="rtl"] .sidebar { transform: translateX(100%); }', $css);
        $this->assertStringContainsString('[dir="rtl"] .sidebar.open { transform: translateX(0); }', $css);
    }
}
