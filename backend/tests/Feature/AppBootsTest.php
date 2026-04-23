<?php

it('returns a 200 response for the root URL', function () {
    $this->get('/')->assertStatus(200);
});

it('exposes the Laravel health check', function () {
    $this->get('/up')->assertStatus(200);
});
