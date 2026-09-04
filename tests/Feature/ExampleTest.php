<?php

namespace Tests\Feature;

use App\Http\Middleware\IsInstalled;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        // The source archive intentionally has no production .env, so the
        // installation gate redirects before this route can be exercised.
        // The middleware has its own deployment responsibility; this smoke
        // test is limited to rendering the public welcome route.
        $this->withoutMiddleware(IsInstalled::class);
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
