<?php

namespace Tests\Unit;

use App\Models\LandingContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_empty_array_when_no_row_exists(): void
    {
        $this->assertSame([], LandingContent::current());
    }

    public function test_current_returns_stored_content(): void
    {
        LandingContent::create(['id' => 1, 'content' => ['hero_title_lead' => 'Judul Baru']]);

        $this->assertSame(['hero_title_lead' => 'Judul Baru'], LandingContent::current());
    }
}
