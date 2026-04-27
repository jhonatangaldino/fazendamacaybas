<?php

use App\Domain\Auth\Services\TemporaryPasswordService;
use App\Mail\BoasVindasUsuario;
use App\Mail\SenhaRegenerada;
use App\Models\User;
use App\Support\PasswordGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\artisan;

beforeEach(function () {
    $this->seed();
    Mail::fake();
});

describe('PasswordGenerator', function () {
    it('gera 8 caracteres alfanuméricos sem ambíguos por padrão', function () {
        $senha = PasswordGenerator::make();

        expect(strlen($senha))->toBe(8);
        expect($senha)->toMatch('/^[A-Za-z0-9]+$/');
        // Sem ambíguos
        expect($senha)->not->toContain('0');
        expect($senha)->not->toContain('O');
        expect($senha)->not->toContain('1');
        expect($senha)->not->toContain('l');
        expect($senha)->not->toContain('I');
    });

    it('aceita tamanho customizado', function () {
        expect(strlen(PasswordGenerator::make(12)))->toBe(12);
        expect(strlen(PasswordGenerator::make(20)))->toBe(20);
    });

    it('rejeita tamanho menor que 4', function () {
        PasswordGenerator::make(3);
    })->throws(InvalidArgumentException::class);

    it('gera senhas diferentes em chamadas consecutivas', function () {
        $senhas = collect()->times(20, fn () => PasswordGenerator::make());
        expect($senhas->unique()->count())->toBeGreaterThan(15); // tolera colisões raras
    });
});

describe('TemporaryPasswordService::issueWelcome', function () {
    it('gera senha, persiste hash e envia email de boas-vindas', function () {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'email' => 'novo@fazendamacaybas.com.br',
        ]);

        $senha = app(TemporaryPasswordService::class)->issueWelcome($user);
        $user->refresh();

        expect($senha)->toMatch('/^[A-Za-z0-9]{8}$/');
        expect(Hash::check($senha, $user->password))->toBeTrue();
        expect($user->temp_password_plaintext)->toBe($senha);
        expect($user->must_change_password)->toBeTrue();
        expect($user->password_expires_at)->not->toBeNull();
        expect($user->password_expires_at->isFuture())->toBeTrue();

        Mail::assertSent(BoasVindasUsuario::class, fn ($m) => $m->hasTo('novo@fazendamacaybas.com.br'));
    });

    it('expiração definida em 2 horas a partir de agora', function () {
        $user = User::factory()->create(['tenant_id' => 1]);
        Carbon::setTestNow(now()); // freeze

        app(TemporaryPasswordService::class)->issueWelcome($user);

        $diff = $user->fresh()->password_expires_at->diffInMinutes(now());
        expect($diff)->toBeGreaterThanOrEqual(119)->toBeLessThanOrEqual(120);

        Carbon::setTestNow();
    });
});

describe('TemporaryPasswordService::regenerate', function () {
    it('gera nova senha e envia email de "senha regenerada"', function () {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'must_change_password' => true,
            'temp_password_plaintext' => 'OldP4ss1',
        ]);
        $oldHash = $user->password;

        $nova = app(TemporaryPasswordService::class)->regenerate($user);
        $user->refresh();

        expect($nova)->not->toBe('OldP4ss1');
        expect(Hash::check($nova, $user->password))->toBeTrue();
        expect($user->password)->not->toBe($oldHash);
        expect($user->temp_password_plaintext)->toBe($nova);

        Mail::assertSent(SenhaRegenerada::class);
    });
});

describe('TemporaryPasswordService::clearOnPasswordChange', function () {
    it('limpa plaintext, expiração e flag must_change ao trocar senha', function () {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'must_change_password' => true,
            'temp_password_plaintext' => 'TempP4ss',
            'password_expires_at' => now()->addHours(2),
        ]);

        app(TemporaryPasswordService::class)->clearOnPasswordChange($user);
        $user->refresh();

        expect($user->temp_password_plaintext)->toBeNull();
        expect($user->password_expires_at)->toBeNull();
        expect($user->must_change_password)->toBeFalse();
    });
});

describe('Senha temporária visibilidade e expiração', function () {
    it('temporaryPasswordIsVisible retorna true só se must_change=true E plaintext existe', function () {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'must_change_password' => true,
            'temp_password_plaintext' => 'V1s1bleP',
        ]);
        expect($user->temporaryPasswordIsVisible())->toBeTrue();

        $user->update(['must_change_password' => false]);
        expect($user->fresh()->temporaryPasswordIsVisible())->toBeFalse();
    });

    it('temporaryPasswordIsExpired retorna true só se must_change E expires_at no passado', function () {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'must_change_password' => true,
            'password_expires_at' => now()->subHour(),
        ]);
        expect($user->temporaryPasswordIsExpired())->toBeTrue();

        $user->update(['password_expires_at' => now()->addHour()]);
        expect($user->fresh()->temporaryPasswordIsExpired())->toBeFalse();
    });
});
