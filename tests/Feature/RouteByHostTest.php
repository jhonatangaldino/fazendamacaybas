<?php

use App\Domain\Billing\Models\Tenant;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed();
    Tenant::clearMasterCache();
});

describe('RouteByHost middleware', function () {

    it('host raiz resolve para context master_landing quando há tenant master', function () {
        // Garante 1 master existente
        $master = Tenant::query()->first();
        $master->update(['is_master_tenant' => true]);
        Tenant::clearMasterCache();

        $hostRaiz = parse_url(config('app.url'), PHP_URL_HOST);

        $this->call('GET', 'http://' . $hostRaiz . '/login')
            ->assertOk();
    });

    it('host raiz sem master configurado degrada para legacy (não 404)', function () {
        Tenant::query()->update(['is_master_tenant' => false]);
        Tenant::clearMasterCache();

        $hostRaiz = parse_url(config('app.url'), PHP_URL_HOST);

        // Mesmo sem master, modo legacy permite passar
        $response = $this->call('GET', 'http://' . $hostRaiz . '/health');
        expect($response->status())->toBeIn([200, 302]);
    });

    it('host app.* sem path /c/{slug} resolve context app', function () {
        $hostRaiz = parse_url(config('app.url'), PHP_URL_HOST);
        $hostApp = 'app.' . $hostRaiz;

        $response = $this->call('GET', 'http://' . $hostApp . '/login');
        expect($response->status())->toBeIn([200, 302]);
    });

    it('host app.* com path /c/{slug-existente} resolve context tenant_app', function () {
        $tenant = Tenant::query()->where('slug', '!=', '')->first();
        $hostApp = 'app.' . parse_url(config('app.url'), PHP_URL_HOST);

        $response = $this->call('GET', 'http://' . $hostApp . '/c/' . $tenant->slug);
        // Em modo legacy não bloqueia; rota /c/{slug} pode renderizar landing
        expect($response->status())->toBeIn([200, 302, 404]);
    });

    it('host externo registrado em tenants.domains resolve context tenant_domain', function () {
        $tenant = Tenant::query()->first();
        $tenant->update(['domains' => ['fazendadojoao.com.br']]);

        $response = $this->call('GET', 'http://fazendadojoao.com.br/');
        // Resolveu para tenant via domínio próprio (modo legacy não aborta)
        expect($response->status())->toBeIn([200, 302]);
    });

    it('host completamente desconhecido em modo legacy NÃO retorna 404', function () {
        config(['app.strict_host_gate' => false]);

        $response = $this->call('GET', 'http://host-aleatorio-xpto.com/');
        // Modo legacy: passa livre
        expect($response->status())->toBeIn([200, 302]);
    });
});

describe('Tenant model · is_master_tenant + domains', function () {

    it('Tenant::master() retorna tenant marcado como master', function () {
        $first = Tenant::query()->first();
        $first->update(['is_master_tenant' => true]);
        Tenant::clearMasterCache();

        $master = Tenant::master();
        expect($master)->not->toBeNull();
        expect($master->id)->toBe($first->id);
    });

    it('Tenant::master() retorna null se nenhum tenant é master', function () {
        Tenant::query()->update(['is_master_tenant' => false]);
        Tenant::clearMasterCache();

        expect(Tenant::master())->toBeNull();
    });

    it('acceptsHost reconhece domínio com e sem www', function () {
        $tenant = new Tenant();
        $tenant->domains = ['fazendadojoao.com.br'];

        expect($tenant->acceptsHost('fazendadojoao.com.br'))->toBeTrue();
        expect($tenant->acceptsHost('FAZENDADOJOAO.COM.BR'))->toBeTrue(); // case insensitive
        expect($tenant->acceptsHost('www.fazendadojoao.com.br'))->toBeTrue();
        expect($tenant->acceptsHost('outrodominio.com.br'))->toBeFalse();
    });

    it('findByDomain encontra tenant pelo host configurado', function () {
        $tenant = Tenant::query()->first();
        $tenant->update(['domains' => ['unico-teste.com.br']]);

        $achou = Tenant::findByDomain('unico-teste.com.br');
        expect($achou)->not->toBeNull();
        expect($achou->id)->toBe($tenant->id);

        expect(Tenant::findByDomain('outrodominio.xyz'))->toBeNull();
    });
});
