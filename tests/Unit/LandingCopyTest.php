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

    public function test_feature_groups_overrides_title_and_item_independently_without_losing_siblings(): void
    {
        // Test 1: Override only group 1's title, verify items remain at default and other groups unaffected
        $copy1 = new LandingCopy([
            'feature_groups' => [
                1 => ['title' => 'Monitoring Kustom'],
            ],
        ]);

        $groups1 = $copy1->featureGroups();

        // Group 0 title unchanged
        $this->assertSame('Operasional', $groups1[0]['title']);

        // Group 1 title overridden
        $this->assertSame('Monitoring Kustom', $groups1[1]['title']);

        // Group 1 items still at defaults
        $this->assertSame('BBM anti-kebocoran, ditandai otomatis', $groups1[1]['items'][0]);
        $this->assertSame('Laporan pendapatan dan utilisasi', $groups1[1]['items'][1]);
        $this->assertSame('Export PDF / Excel', $groups1[1]['items'][2]);
        $this->assertSame('GPS live di peta', $groups1[1]['items'][3]);

        // Group 2 and 3 titles unchanged
        $this->assertSame('Produktivitas', $groups1[2]['title']);
        $this->assertSame('Pengalaman Pelanggan', $groups1[3]['title']);

        // Test 2: Override only an item in group 1, verify title remains at default and other items unaffected
        $copy2 = new LandingCopy([
            'feature_groups' => [
                1 => ['items' => [2 => 'Export custom items saja']],
            ],
        ]);

        $groups2 = $copy2->featureGroups();

        // Group 1 title still at default (not overridden)
        $this->assertSame('Monitoring', $groups2[1]['title']);

        // Group 1 items: only index 2 overridden, others at default
        $this->assertSame('BBM anti-kebocoran, ditandai otomatis', $groups2[1]['items'][0]);
        $this->assertSame('Laporan pendapatan dan utilisasi', $groups2[1]['items'][1]);
        $this->assertSame('Export custom items saja', $groups2[1]['items'][2]); // overridden
        $this->assertSame('GPS live di peta', $groups2[1]['items'][3]);

        // Group 0 and other groups unaffected
        $this->assertSame('Operasional', $groups2[0]['title']);
        $this->assertSame('Produktivitas', $groups2[2]['title']);
    }
}
