<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignupNavLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_links_to_signup_pricing(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('signup.pricing'), false);
    }
}
