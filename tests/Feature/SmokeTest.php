<?php

use function Pest\Laravel\get;

it('landing page carrega', function () {
    $this->seed();
    get('/')->assertOk()->assertSee('Fazenda Macaybas');
});

it('health check retorna OK', function () {
    get('/health')->assertOk()->assertJsonFragment(['status' => 'ok']);
});

it('login page renderiza', function () {
    get('/login')->assertOk();
});

it('admin area redireciona quando deslogado', function () {
    get('/admin/dashboard')->assertRedirect('/login');
});
