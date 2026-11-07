<?php

declare(strict_types=1);

it('sends visitors from the home page to the app', function (): void {
    $this->get('/')->assertRedirect('/app');
});

it('shows the sign-in page to guests', function (): void {
    $this->get('/app/login')->assertOk()->assertSee('Sign in');
});

it('keeps the app behind sign-in', function (): void {
    $this->get('/app')->assertRedirect('/app/login');
});
