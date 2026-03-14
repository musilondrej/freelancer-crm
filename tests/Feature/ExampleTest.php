<?php

test('the root route redirects to admin', function (): void {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});
