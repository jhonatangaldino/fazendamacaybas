# Plano SaaS Multi-Tenant · Índice

Planejamento completo da evolução do sistema Fazenda Macaybas para ERP Rural SaaS Multi-Tenant comercializável.

## Documentos

| Documento | Conteúdo |
|---|---|
| [FASE-1-DIAGNOSTICO-SAAS.md](./FASE-1-DIAGNOSTICO-SAAS.md) | **Fases 1 e 2** · Diagnóstico do sistema atual, lógica operacional da fazenda real, autocrítica, riscos e estratégia de preservação |
| [FASES-3-4-5-ARQUITETURA-UX.md](./FASES-3-4-5-ARQUITETURA-UX.md) | **Fases 3, 4 e 5** · Arquitetura final, modelagem final (schema + metadata JSON), UX detalhado tela a tela |

## Progressão

```
FASE 1  Diagnóstico + operação real        ✅ concluída
FASE 2  Riscos e preservação               ✅ concluída
FASE 3  Arquitetura final                  ✅ concluída
FASE 4  Modelagem final                    ✅ concluída
FASE 5  UX detalhado                       ✅ concluída
FASE 6  Implementação (R1 → R7)            ⏸ aguardando 3 decisões
```

## Decisões bloqueantes antes da FASE 6

1. **Preços dos planos** (Essencial / Profissional / Empresarial)
2. **Gateway PIX** (BB / Inter / Efi / Asaas)
3. **Ordem das releases R1–R7**

## Princípios-lei (congelados desde FASE 2)

- Não duplicar estruturas centrais: `animal_events`, `stock_items`, `stock_movements`, `financial_transactions`, `animal_lots` são fonte única
- Migrations aditivas · nunca destrutivas
- `metadata` JSON como coringa para campos específicos de evento
- UX para leigo: 1 ação por tela, botão grande, mobile-first
- Feature flags em toda mudança disruptiva
- Observers idempotentes via `(reference_type, reference_id)`
