<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('home document includes csrf meta for javascript fetch clients', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('name="csrf-token"', false);
});
