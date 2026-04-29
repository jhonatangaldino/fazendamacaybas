# Sentry · Monitoramento de erros em produção

Pra capturar bugs reais de usuários antes que reportem (Opção B do plano).

---

## 1. Criar conta Sentry (5 min · grátis até 5k eventos/mês)

1. Acessar https://sentry.io/signup
2. Login com Google/GitHub
3. Criar **organization**: `fazenda-macaybas`
4. Criar **project**: `macaybas-prod` (Laravel)
5. Sentry mostra o **DSN**: `https://abc123@o123.ingest.sentry.io/456`
6. Anote o DSN

## 2. Instalar pacote no projeto

Local:
```bash
composer require sentry/sentry-laravel
```

Produção (via deploy automático):
```bash
git add composer.json composer.lock
git commit -m "Add sentry/sentry-laravel"
git push
bash scripts/deploy-local.sh
```

## 3. Configurar `.env` em produção

Via SSH:
```bash
ssh -i ~/.ssh/macaybas_deploy -p 65002 -o StrictHostKeyChecking=no u931382046@147.93.14.208 \
  'echo "SENTRY_LARAVEL_DSN=https://abc123@o123.ingest.sentry.io/456" >> /home/u931382046/domains/fazendamacaybas.com.br/shared/.env'
```

Trocar pelo DSN real.

## 4. Registrar exception handler

Adicionar em `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    // Existing handlers ...

    // Sentry: captura tudo que vire exception não tratada
    \Sentry\Laravel\Integration::handles($exceptions);
})
```

## 5. Frontend (opcional · Vue + Inertia)

Pra capturar erros JS no browser:

```bash
npm install @sentry/vue
```

Em `resources/js/app.js`:

```js
import * as Sentry from '@sentry/vue';

Sentry.init({
    app,
    dsn: 'https://abc123@o123.ingest.sentry.io/456',  // mesmo DSN ou um separado pro frontend
    environment: import.meta.env.MODE,
    integrations: [
        Sentry.browserTracingIntegration(),
        Sentry.replayIntegration(),
    ],
    tracesSampleRate: 0.1,  // 10% das requisições
    replaysSessionSampleRate: 0.1,
});
```

## 6. Confirmar funcionando

Após deploy, acessar `/sentry-test` (rota temporária) que dispara `throw new \Exception('Sentry test')`. Confirmar que aparece no dashboard Sentry em ~10s.

---

## SLA de fix em produção (Opção B)

| Severidade | Definição | Fix em |
|------------|-----------|--------|
| **Crítico** | Sistema fora do ar · perda de dados · vazamento entre tenants · não conseguir logar | **4 horas** |
| **Alto** | Funcionalidade principal quebrada (criar animal/transação não salva, cálculo errado) | **24 horas** |
| **Médio** | Funcionalidade secundária quebrada · UX confusa em fluxo importante | **1 semana** |
| **Baixo** | Polish · cosmético · texto incorreto | **Próximo sprint** |

Sentry alerta via email/Slack quando crítico/alto entra em produção. Eu (Claude) fixo conforme você reporta — você não precisa esperar usuários reclamarem.

---

## Custo

- **Free tier**: 5.000 eventos/mês · suficiente pra começar
- Se exceder: $26/mês plan Team (50k eventos)

## Alternativas se preferir auto-hospedar

- **Bugsnag**: similar, plano free 7.500 eventos/mês
- **GlitchTip**: open-source, self-hosted (você instala no Hostinger ou em VPS separada)
- **Logflare** + **Better Stack**: mais leve, free tier generoso

---

**Status atual**: documento pronto. Pra ativar, criar conta Sentry e me passar o DSN. Eu ativo no projeto.
