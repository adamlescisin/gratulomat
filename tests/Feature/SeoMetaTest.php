<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_have_canonical_description_and_open_graph(): void
    {
        config(['gratulomat.site_mode' => 'coming_soon', 'gratulomat.marketing_url' => 'https://gratulomat.test']);

        foreach (['/' => 'https://gratulomat.test/', '/ochrana-soukromi' => 'https://gratulomat.test/ochrana-soukromi',
            '/obchodni-podminky' => 'https://gratulomat.test/obchodni-podminky', '/pravidla-pro-hosty' => 'https://gratulomat.test/pravidla-pro-hosty'] as $path => $canonical) {
            $this->get($path)->assertOk()
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
                ->assertSee('<meta name="description" content="', false)
                ->assertSee('<meta property="og:title"', false)
                ->assertSee('<meta property="og:url" content="'.$canonical.'">', false);
        }
    }
}
