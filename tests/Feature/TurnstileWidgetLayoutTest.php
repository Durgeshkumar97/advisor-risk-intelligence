<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

/**
 * Both Turnstile containers reserve the Managed widget's 65px before its
 * script renders into them. Without it the container starts at 0px and the
 * widget pushes everything below it down after load — a layout shift on
 * /register for every visitor, and on the home-page trial form for anyone
 * arriving at /#free-trial, where the form is on screen at load.
 */
it('reserves the widget height on the home-page trial form', function () {
    config()->set('services.turnstile.site_key', 'site-key-for-test');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('class="cf-turnstile" data-sitekey="site-key-for-test" style="margin:0 auto;min-height:65px;"', false);
});

it('reserves the widget height on the register form', function () {
    config()->set('services.turnstile.site_key', 'site-key-for-test');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('class="cf-turnstile" data-sitekey="site-key-for-test" style="min-height:65px;"', false);
});
