<?php

namespace Tests\Unit;

use App\Content\LandingCopy;
use PHPUnit\Framework\TestCase;

class LandingCopyTest extends TestCase
{
    public function test_returns_defaults_when_no_data_stored(): void
    {
        $copy = new LandingCopy([]);

        $this->assertSame('Kelola seluruh operasional armada', $copy->heroTitleLead());
        $this->assertSame('Coba Gratis 14 Hari', $copy->ctaLabel());
        $this->assertCount(5, $copy->painItems());
        $this->assertSame('Sulit tahu posisi kendaraan', $copy->painItems()[0]['title']);
    }

    public function test_overrides_single_field_without_losing_others(): void
    {
        $copy = new LandingCopy(['hero_title_lead' => 'Judul Kustom']);

        $this->assertSame('Judul Kustom', $copy->heroTitleLead());
        $this->assertSame('dalam satu platform.', $copy->heroTitleReveal()); // tetap default
    }

    public function test_overrides_single_item_in_a_group_without_losing_siblings(): void
    {
        $copy = new LandingCopy([
            'pain_items' => [
                1 => ['title' => 'Judul Baru Item Kedua'],
            ],
        ]);

        $items = $copy->painItems();
        $this->assertSame('Sulit tahu posisi kendaraan', $items[0]['title']); // item 0 tetap default
        $this->assertSame('Judul Baru Item Kedua', $items[1]['title']); // item 1 diganti
        $this->assertSame('Rekap bulanan baru jadi tanggal 10. Keputusan diambil pakai firasat.', $items[1]['text']); // text item 1 tetap default (tak dikirim)
    }

    public function test_empty_string_treated_same_as_missing(): void
    {
        $copy = new LandingCopy(['cta_label' => '']);

        $this->assertSame('Coba Gratis 14 Hari', $copy->ctaLabel());
    }

    public function test_simple_list_override_per_index(): void
    {
        $copy = new LandingCopy(['trust_items' => [0 => 'Label Baru']]);

        $items = $copy->trustItems();
        $this->assertSame('Label Baru', $items[0]);
        $this->assertSame('Platform cloud', $items[1]); // index 1 tetap default
    }
}
