<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests from admin dashboard to login', function (): void {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

it('renders admin login page', function (): void {
    $this->get('/admin/login')
        ->assertSuccessful();
});
