# Roadmap — Evolução após o 1º deploy

O sistema sobe em produção com o **core funcional completo**: autenticação, RBAC, dashboard, usuários, CMS da landing, financeiro, rebanho e parceiros. Os demais módulos têm banco + models prontos, faltando apenas as telas CRUD — que serão incrementadas em sprints curtos sem bloquear a operação.

## Sprint 2 — Agrícola + Estoque (2 semanas)

- [ ] CRUD de **talhões** (Field) com mapa opcional (Leaflet/Google Maps)
- [ ] CRUD de **plantios** (vincula talhão + cultura + safra)
- [ ] Lançamento de **colheitas** e cálculo automático de produtividade/ha
- [ ] Registro de **aplicações** (adubação, herbicida, etc.)
- [ ] CRUD de **itens de estoque** com categoria, unidade, estoque mínimo
- [ ] **Entradas / saídas / transferências** entre armazéns
- [ ] Dashboard com alerta automático de estoque baixo

## Sprint 3 — Máquinas + Tarefas (2 semanas)

- [ ] CRUD de **veículos** (placa, marca, modelo, ano)
- [ ] **Ordens de manutenção** (preventiva / corretiva)
- [ ] Integração com financeiro (manutenção gera conta a pagar)
- [ ] Controle de **horímetro/quilometragem**
- [ ] CRUD de **tarefas** com atribuição a funcionário(s)
- [ ] **Checklists** com itens marcáveis
- [ ] Kanban-like view por status

## Sprint 4 — Documentos + Relatórios (1 semana)

- [ ] Upload de **documentos** com categorização (contratos, NFs, comprovantes, sanitários)
- [ ] Filtros avançados (data, categoria, parceiro, vencimento)
- [ ] Alerta de documento vencendo em 30 dias
- [ ] **Relatórios**:
  - Fluxo de caixa mensal/anual
  - Rebanho por espécie/lote
  - Giro de estoque
  - Custo por talhão/safra
  - Exportação PDF e Excel

## Sprint 5 — Rebanho avançado (1 semana)

- [ ] **Eventos** do animal (pesagem, vacinação, medicação, reprodução, nascimento, morte)
- [ ] Linha do tempo por animal
- [ ] Cálculo de **GMD** (ganho médio diário)
- [ ] Calendário de vacinação
- [ ] Alertas automáticos (vacina atrasada, inseminação, etc.)

## Sprint 6 — Funcionários (1 semana)

- [ ] CRUD completo de **funcionários**
- [ ] Contratos anexados
- [ ] Histórico de admissão / demissão / cargo
- [ ] Vinculação com tarefas e apontamentos

## Sprint 7 — CMS avançado (1 semana)

- [ ] Editor WYSIWYG (Tiptap) nas seções de texto longo
- [ ] **Preview** antes de publicar (modo "mostrar como ficaria")
- [ ] Versionamento de seções (histórico de mudanças)
- [ ] Drag-and-drop real para reordenar seções (vue-draggable)
- [ ] Upload em massa de imagens para galeria
- [ ] SEO por página (sitemap.xml dinâmico)

## Sprint 8+ — Melhorias contínuas

- [ ] **2FA** (autenticação em dois fatores) opcional para admin master
- [ ] **API REST** para integração futura com app mobile
- [ ] **Notificações** push (web) e e-mail de alertas
- [ ] **Multi-fazenda** (suportar múltiplas propriedades)
- [ ] **Importação em massa** (CSV de rebanho, lançamentos financeiros)
- [ ] Dashboard com **gráficos** (Chart.js) de evolução financeira, rebanho, produtividade
- [ ] Integração com **Serpro / SEFAZ** para emissão de NF-e de produção
- [ ] Integração com **serviços meteorológicos** (alertas de chuva, geada)

## Infraestrutura futura

Quando a fazenda crescer:

- [ ] Migrar de Hostinger Business para **VPS** dedicado (RAM/CPU garantidos)
- [ ] Adicionar **Redis** para cache e filas
- [ ] **Object storage** (S3 / Cloudflare R2) para uploads e backups
- [ ] **CDN** (Cloudflare) na frente da landing
- [ ] **Monitoramento** (Sentry para erros, UptimeRobot para disponibilidade)
