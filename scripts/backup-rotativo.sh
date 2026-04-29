#!/usr/bin/env bash
#
# backup-rotativo.sh — backup de 10 em 10 dias com validação prévia.
#
# Estratégia: gera novo backup em /tmp, valida (tamanho + integridade),
# e SÓ substitui o "current" se o novo for válido. Mantém só 1 backup
# vivo (não acumula = não onera disco).
#
# Roda no servidor via cron diário; auto-pula se o último backup tem
# menos de 10 dias. Cron line:
#   0 3 * * * bash /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/backup-rotativo.sh
#
# Backups gerados em shared/backups/:
#   - db-current.sql.gz       — mysqldump comprimido
#   - storage-current.tar.gz  — uploads (animais, comprovantes, CMS)
#   - env-current.txt         — cópia do .env (perm 600)
#   - last-backup.txt         — timestamp do último backup OK
#   - backup.log              — log de todas execuções

set -euo pipefail

# ── Config ─────────────────────────────────────────────────────────
INTERVALO_DIAS="${INTERVALO_DIAS:-10}"
DEPLOY_BASE="${DEPLOY_BASE:-/home/u931382046/domains/fazendamacaybas.com.br}"
BACKUPS_DIR="$DEPLOY_BASE/shared/backups"
ENV_FILE="$DEPLOY_BASE/shared/.env"
LOG="$BACKUPS_DIR/backup.log"
STAMP=$(date +%Y%m%d-%H%M%S)
TODAY_EPOCH=$(date +%s)

mkdir -p "$BACKUPS_DIR"
chmod 700 "$BACKUPS_DIR"  # só dono acessa (contém .env)

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG"
}

# ── 0. Auto-skip se último backup tem < INTERVALO_DIAS dias ───────
if [ -f "$BACKUPS_DIR/last-backup.txt" ]; then
    LAST_STAMP=$(cat "$BACKUPS_DIR/last-backup.txt" 2>/dev/null || echo "0")
    if [ -n "$LAST_STAMP" ] && [ "$LAST_STAMP" != "0" ]; then
        # Converte stamp YYYYMMDD-HHMMSS → epoch
        LAST_DATE="${LAST_STAMP:0:8}"
        LAST_TIME="${LAST_STAMP:9:6}"
        LAST_FORMATTED="${LAST_DATE:0:4}-${LAST_DATE:4:2}-${LAST_DATE:6:2} ${LAST_TIME:0:2}:${LAST_TIME:2:2}:${LAST_TIME:4:2}"
        LAST_EPOCH=$(date -d "$LAST_FORMATTED" +%s 2>/dev/null || echo 0)
        if [ "$LAST_EPOCH" -gt 0 ]; then
            DIAS_DESDE_ULTIMO=$(( (TODAY_EPOCH - LAST_EPOCH) / 86400 ))
            if [ "$DIAS_DESDE_ULTIMO" -lt "$INTERVALO_DIAS" ]; then
                log "⏭ Pulando · último backup há $DIAS_DESDE_ULTIMO dias (intervalo: $INTERVALO_DIAS)"
                exit 0
            fi
        fi
    fi
fi

log "═══════════════════════════════════════════"
log "Início backup · $STAMP"

# ── 1. Validar pré-requisitos ──────────────────────────────────────
if [ ! -f "$ENV_FILE" ]; then
    log "❌ .env não encontrado em $ENV_FILE"
    exit 1
fi

# Lê credenciais do banco
DB_HOST=$(grep '^DB_HOST=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_DATABASE=$(grep '^DB_DATABASE=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep '^DB_USERNAME=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep '^DB_PASSWORD=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")

if [ -z "$DB_HOST" ] || [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
    log "❌ Credenciais de banco incompletas no .env"
    exit 1
fi

# ── 2. Backup banco em /tmp ────────────────────────────────────────
TMP_DB="/tmp/db-backup-$STAMP.sql.gz"
log "▶ mysqldump → $TMP_DB"

if ! mysqldump \
    -h "$DB_HOST" \
    -u "$DB_USERNAME" \
    -p"$DB_PASSWORD" \
    "$DB_DATABASE" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --no-tablespaces \
    --add-drop-table 2>>"$LOG" \
    | gzip > "$TMP_DB"; then
    log "❌ mysqldump falhou — backup atual preservado"
    rm -f "$TMP_DB"
    exit 1
fi

# Valida tamanho mínimo (banco com schema completo tem pelo menos 30 KB)
SIZE_DB=$(stat -c%s "$TMP_DB" 2>/dev/null || echo 0)
if [ "$SIZE_DB" -lt 30720 ]; then  # < 30 KB seguro suspeito (sem nem schema)
    log "❌ Banco backup muito pequeno: $SIZE_DB bytes — inválido. Mantendo current."
    rm -f "$TMP_DB"
    exit 1
fi

# Valida integridade — usa grep -c (lê tudo, evita SIGPIPE com pipefail).
# `grep -q` faz exit cedo quando acha, mas isso causa SIGPIPE no zcat
# que junto com `set -o pipefail` faz a condição IF falhar mesmo com match.
TABELAS_ESPERADAS="users tenants animals manual_envios"
for tabela in $TABELAS_ESPERADAS; do
    PADRAO_BUSCA="CREATE TABLE \`${tabela}\`"
    COUNT=$(zcat "$TMP_DB" | LC_ALL=C grep -acF "$PADRAO_BUSCA" || true)
    if [ "$COUNT" -eq 0 ]; then
        log "❌ Backup sem tabela $tabela — corrompido."
        log "   Padrão: '$PADRAO_BUSCA' · matches: $COUNT"
        mv "$TMP_DB" "${TMP_DB}.failed"
        exit 1
    fi
done
log "✅ Banco validado · $(numfmt --to=iec "$SIZE_DB" 2>/dev/null || echo "$SIZE_DB B") · $(zcat "$TMP_DB" | wc -l) linhas SQL"

# ── 3. Backup storage em /tmp ──────────────────────────────────────
TMP_STORAGE="/tmp/storage-backup-$STAMP.tar.gz"
log "▶ tar storage/app/public → $TMP_STORAGE"

if [ ! -d "$DEPLOY_BASE/shared/storage/app/public" ]; then
    log "⚠️ storage/app/public não existe — pulando esse backup"
    SIZE_STORAGE=0
else
    if ! tar -czf "$TMP_STORAGE" \
        -C "$DEPLOY_BASE/shared/storage" \
        app/public 2>>"$LOG"; then
        log "❌ tar storage falhou"
        rm -f "$TMP_DB" "$TMP_STORAGE"
        exit 1
    fi

    SIZE_STORAGE=$(stat -c%s "$TMP_STORAGE" 2>/dev/null || echo 0)
    if [ "$SIZE_STORAGE" -lt 1024 ]; then
        log "❌ Storage backup muito pequeno: $SIZE_STORAGE bytes"
        rm -f "$TMP_DB" "$TMP_STORAGE"
        exit 1
    fi
    log "✅ Storage validado · $(numfmt --to=iec "$SIZE_STORAGE" 2>/dev/null || echo "$SIZE_STORAGE B")"
fi

# ── 4. Tudo OK · promove backups novos pra current ─────────────────
log "▶ Substituindo current pelos novos backups"

mv -f "$TMP_DB" "$BACKUPS_DIR/db-current.sql.gz"
chmod 600 "$BACKUPS_DIR/db-current.sql.gz"

if [ -f "$TMP_STORAGE" ]; then
    mv -f "$TMP_STORAGE" "$BACKUPS_DIR/storage-current.tar.gz"
    chmod 600 "$BACKUPS_DIR/storage-current.tar.gz"
fi

# .env: copia uma versão atual junto (perm 600 — só dono lê)
cp -f "$ENV_FILE" "$BACKUPS_DIR/env-current.txt"
chmod 600 "$BACKUPS_DIR/env-current.txt"

# Marca timestamp
echo "$STAMP" > "$BACKUPS_DIR/last-backup.txt"

# ── 5. Resumo ──────────────────────────────────────────────────────
TOTAL_SIZE=$((SIZE_DB + SIZE_STORAGE))
log "✅ Backup OK · banco $(numfmt --to=iec "$SIZE_DB" 2>/dev/null || echo "$SIZE_DB B") · storage $(numfmt --to=iec "$SIZE_STORAGE" 2>/dev/null || echo "$SIZE_STORAGE B") · total $(numfmt --to=iec "$TOTAL_SIZE" 2>/dev/null || echo "$TOTAL_SIZE B")"
log "Próximo backup automático em $INTERVALO_DIAS dias"
log "═══════════════════════════════════════════"

exit 0
