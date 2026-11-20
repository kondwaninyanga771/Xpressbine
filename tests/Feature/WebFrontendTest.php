<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebFrontendTest extends TestCase
{
    public function test_inertia_pages_render_without_touching_the_api(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Feed'));
        $this->get('/discover')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Discover'));
        $this->get('/map')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Map'));
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Login'));
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Register'));
        $this->get('/messages')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Messages'));
        $this->get('/bookings/4')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Booking')
            ->where('bookingId', 4));
        $this->get('/providers/2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Provider')
            ->where('providerId', 2));
    }
}
