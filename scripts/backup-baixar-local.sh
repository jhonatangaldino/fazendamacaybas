#!/usr/bin/env bash
#
# backup-baixar-local.sh — baixa backup do servidor pro seu PC.
#
# Roda LOCALMENTE no seu computador (Windows Git Bash, Mac, Linux).
# Cria pasta com data + baixa os 3 arquivos do servidor.
# Mantém os últimos 6 meses (rotativo). Antigos são deletados.
#
# Uso manual:
#   bash scripts/backup-baixar-local.sh
#
# Uso automatizado (Windows Task Scheduler / cron):
#   Configurar pra rodar dia 1 de cada mês, manhã.
#
# IMPORTANTE: este script é a ÚNICA proteção contra "servidor wipado".
# Se servidor for comprometido, é o backup local que vai te salvar.

set -euo pipefail

# ── Config ─────────────────────────────────────────────────────────
SSH_KEY="${SSH_KEY:-$HOME/.ssh/macaybas_deploy}"
SSH_HOST="${SSH_HOST:-147.93.14.208}"
SSH_PORT="${SSH_PORT:-65002}"
SSH_USER="${SSH_USER:-u931382046}"
DEPLOY_BASE="${DEPLOY_BASE:-/home/u931382046/domains/fazendamacaybas.com.br}"

LOCAL_BASE="${LOCAL_BACKUPS_DIR:-$HOME/backups-macaybas}"
DATA=$(date +%Y-%m-%d)
LOCAL_DIR="$LOCAL_BASE/$DATA"
KEEP_MONTHS="${KEEP_MONTHS:-6}"

# ── Pré-checagens ──────────────────────────────────────────────────
echo "═══════════════════════════════════════════════════════════════"
echo "║ Backup off-server · download para $LOCAL_BASE"
echo "═══════════════════════════════════════════════════════════════"

if [ ! -f "$SSH_KEY" ]; then
    echo "❌ Chave SSH não encontrada em $SSH_KEY"
    exit 1
fi

mkdir -p "$LOCAL_DIR"
chmod 700 "$LOCAL_BASE" "$LOCAL_DIR"

SSH_OPTS=(-i "$SSH_KEY" -o StrictHostKeyChecking=no -o ConnectTimeout=15)

# ── 1. Confirma que backup atual no servidor é válido ─────────────
echo ""
echo "▶ Verificando backup atual no servidor..."

REMOTE_LIST=$(ssh -p "$SSH_PORT" "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" \
    "ls -la $DEPLOY_BASE/shared/backups/ 2>/dev/null" || echo "")

if ! echo "$REMOTE_LIST" | grep -q "db-current.sql.gz"; then
    echo "❌ Servidor não tem db-current.sql.gz. Rode o backup-rotativo.sh primeiro."
    exit 1
fi
if ! echo "$REMOTE_LIST" | grep -q "storage-current.tar.gz"; then
    echo "❌ Servidor não tem storage-current.tar.gz."
    exit 1
fi
if ! echo "$REMOTE_LIST" | grep -q "env-current.txt"; then
    echo "❌ Servidor não tem env-current.txt."
    exit 1
fi
echo "  ✅ Os 3 arquivos existem no servidor"

# Última data do backup
LAST_BACKUP=$(ssh -p "$SSH_PORT" "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" \
    "cat $DEPLOY_BASE/shared/backups/last-backup.txt 2>/dev/null" || echo "desconhecido")
echo "  ✅ Backup do servidor é de: $LAST_BACKUP"

# ── 2. Download via scp ────────────────────────────────────────────
echo ""
echo "▶ Baixando backups → $LOCAL_DIR"

scp -P "$SSH_PORT" "${SSH_OPTS[@]}" \
    "$SSH_USER@$SSH_HOST:$DEPLOY_BASE/shared/backups/db-current.sql.gz" \
    "$LOCAL_DIR/db-$DATA.sql.gz"
SIZE_DB=$(stat -c%s "$LOCAL_DIR/db-$DATA.sql.gz" 2>/dev/null || stat -f%z "$LOCAL_DIR/db-$DATA.sql.gz" 2>/dev/null)
echo "  ✅ db-$DATA.sql.gz · $(numfmt --to=iec "$SIZE_DB" 2>/dev/null || echo "$SIZE_DB B")"

scp -P "$SSH_PORT" "${SSH_OPTS[@]}" \
    "$SSH_USER@$SSH_HOST:$DEPLOY_BASE/shared/backups/storage-current.tar.gz" \
    "$LOCAL_DIR/storage-$DATA.tar.gz"
SIZE_STORAGE=$(stat -c%s "$LOCAL_DIR/storage-$DATA.tar.gz" 2>/dev/null || stat -f%z "$LOCAL_DIR/storage-$DATA.tar.gz" 2>/dev/null)
echo "  ✅ storage-$DATA.tar.gz · $(numfmt --to=iec "$SIZE_STORAGE" 2>/dev/null || echo "$SIZE_STORAGE B")"

scp -P "$SSH_PORT" "${SSH_OPTS[@]}" \
    "$SSH_USER@$SSH_HOST:$DEPLOY_BASE/shared/backups/env-current.txt" \
    "$LOCAL_DIR/env-$DATA.txt"
chmod 600 "$LOCAL_DIR/env-$DATA.txt"
echo "  ✅ env-$DATA.txt · perm 600 (só você lê)"

# ── 3. Validação local ─────────────────────────────────────────────
echo ""
echo "▶ Validando backups baixados..."

# Banco: descomprime header e procura tabelas-chave
if ! gunzip -t "$LOCAL_DIR/db-$DATA.sql.gz" 2>/dev/null; then
    echo "❌ db-$DATA.sql.gz está corrompido"
    exit 1
fi
USERS_COUNT=$(zcat "$LOCAL_DIR/db-$DATA.sql.gz" | LC_ALL=C grep -acF "CREATE TABLE \`users\`" || true)
ANIMALS_COUNT=$(zcat "$LOCAL_DIR/db-$DATA.sql.gz" | LC_ALL=C grep -acF "CREATE TABLE \`animals\`" || true)
if [ "$USERS_COUNT" -eq 0 ] || [ "$ANIMALS_COUNT" -eq 0 ]; then
    echo "❌ Banco baixado sem tabelas-chave (users=$USERS_COUNT animals=$ANIMALS_COUNT) — corrompido"
    exit 1
fi
echo "  ✅ Banco válido (users + animals presentes)"

# Storage tar
if ! tar -tzf "$LOCAL_DIR/storage-$DATA.tar.gz" >/dev/null 2>&1; then
    echo "❌ storage-$DATA.tar.gz está corrompido"
    exit 1
fi
ARQUIVOS=$(tar -tzf "$LOCAL_DIR/storage-$DATA.tar.gz" | wc -l)
echo "  ✅ Storage válido · $ARQUIVOS arquivos no tarball"

# Env
if ! grep -q "^APP_KEY=" "$LOCAL_DIR/env-$DATA.txt"; then
    echo "❌ env-$DATA.txt sem APP_KEY"
    exit 1
fi
echo "  ✅ .env válido (tem APP_KEY)"

# ── 4. Rotação local · mantém os últimos N meses ──────────────────
echo ""
echo "▶ Rotação · mantendo últimos $KEEP_MONTHS backups"

# Ordena por data descrescente, pula os N mais novos, deleta o resto
cd "$LOCAL_BASE"
PASTAS=( $(ls -d 20*/ 2>/dev/null | sort -r) )
if [ "${#PASTAS[@]}" -gt "$KEEP_MONTHS" ]; then
    for ((i=KEEP_MONTHS; i<${#PASTAS[@]}; i++)); do
        echo "  🗑 Removendo backup antigo: ${PASTAS[$i]}"
        rm -rf "${PASTAS[$i]}"
    done
fi

# ── 5. Resumo ──────────────────────────────────────────────────────
echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "║ ✅ Backup off-server completo"
echo "║"
echo "║ Local: $LOCAL_DIR"
echo "║ Tamanho: $(du -sh "$LOCAL_DIR" 2>/dev/null | cut -f1)"
echo "║"
echo "║ Backups guardados localmente:"
ls -d "$LOCAL_BASE"/20*/ 2>/dev/null | while read d; do
    echo "║   - $(basename "$d") · $(du -sh "$d" 2>/dev/null | cut -f1)"
done
echo "║"
echo "║ 💡 Próxima execução recomendada: dia 1 do próximo mês."
echo "║    Configure no Windows Task Scheduler ou cron:"
echo "║    bash $0"
echo "═══════════════════════════════════════════════════════════════"
