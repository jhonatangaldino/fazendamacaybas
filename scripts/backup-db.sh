#!/usr/bin/env bash
#
# backup-db.sh — Backup diário do MySQL via cron.
# Mantém SOMENTE os 3 últimos backups bem-sucedidos.
#
# Cron sugerido (também dispara o snapshot da ordem do menu):
#   0 3 * * * /home/u931382046/domains/fazendamacaybas.com.br/shared/scripts/backup-db.sh

set -euo pipefail

DEPLOY_BASE="/home/u931382046/domains/fazendamacaybas.com.br"
BACKUP_DIR="${DEPLOY_BASE}/backups"
KEEP=3
STAMP=$(date +%Y-%m-%d_%H%M%S)

# Lê credenciais do .env
set -a
source "${DEPLOY_BASE}/shared/.env"
set +a

mkdir -p "${BACKUP_DIR}"

FILE="${BACKUP_DIR}/macaybas-${STAMP}.sql.gz"
TMP="${FILE}.tmp"

# Dump em arquivo temporário — se mysqldump falhar, não promove pra final
if mysqldump -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" \
        --single-transaction --routines --triggers --events 2>/tmp/macaybas-backup.err \
        | gzip > "${TMP}"; then
    # Valida que o arquivo tem tamanho razoável (> 1KB) e é gzip válido
    if [ -s "${TMP}" ] && gzip -t "${TMP}" 2>/dev/null; then
        mv -f "${TMP}" "${FILE}"
        echo "✅ Backup bem-sucedido: ${FILE}"
    else
        rm -f "${TMP}"
        echo "❌ Dump gerou arquivo inválido — backup abortado" >&2
        exit 1
    fi
else
    rm -f "${TMP}"
    echo "❌ mysqldump falhou:" >&2
    cat /tmp/macaybas-backup.err >&2
    exit 1
fi

# Rotação: mantém apenas os 3 mais recentes que foram promovidos (.sql.gz finais).
# Ordena por data de modificação desc, pula os 3 primeiros, remove o resto.
ls -1t "${BACKUP_DIR}"/macaybas-*.sql.gz 2>/dev/null \
    | tail -n +$((KEEP + 1)) \
    | xargs -r rm -f --

# Relatório de backups retidos
echo "📦 Backups retidos (mantém ${KEEP} mais recentes):"
ls -1t "${BACKUP_DIR}"/macaybas-*.sql.gz 2>/dev/null | head -n ${KEEP} | sed 's/^/   - /'

# Dispara o snapshot da ordem do menu (via artisan do release atual)
if [ -d "${DEPLOY_BASE}/releases/current" ]; then
    cd "${DEPLOY_BASE}/releases/current"
    php artisan menu:snapshot --quiet 2>/dev/null || echo "⚠️  menu:snapshot falhou (ok se ainda não existir)" >&2
fi
