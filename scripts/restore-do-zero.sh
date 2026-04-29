#!/usr/bin/env bash
#
# restore-do-zero.sh — recupera o sistema a partir dos backups.
#
# Cenário típico: servidor foi limpo OU houve corrupção. Você ainda
# tem os 3 arquivos do shared/backups/. Este script:
#
#   1. Verifica se backups existem e são íntegros
#   2. Restaura .env em shared/.env
#   3. Restaura mysqldump no banco
#   4. Restaura storage/app/public
#   5. Cria symlinks e permissões
#   6. Smoke test (HTTP 200)
#
# Pré-requisitos:
#   - shared/backups/ deve ter db-current.sql.gz + storage-current.tar.gz + env-current.txt
#   - Banco MySQL existente e acessível com credenciais do .env
#   - Código do app já deployado em releases/current
#
# Uso (no servidor):
#   bash scripts/restore-do-zero.sh
#
# Para FORÇAR sem confirmação (script automatizado):
#   FORCE=1 bash scripts/restore-do-zero.sh

set -euo pipefail

DEPLOY_BASE="${DEPLOY_BASE:-/home/u931382046/domains/fazendamacaybas.com.br}"
BACKUPS_DIR="$DEPLOY_BASE/shared/backups"
ENV_TARGET="$DEPLOY_BASE/shared/.env"
STORAGE_TARGET="$DEPLOY_BASE/shared/storage"
RELEASE_CURRENT="$DEPLOY_BASE/releases/current"

echo "═══════════════════════════════════════════════════════════════"
echo "║ Restore do Sistema · Fazenda Macaybas"
echo "║"
echo "║ Esse script vai:"
echo "║   1. Restaurar shared/.env (sobrescreve atual)"
echo "║   2. DERRUBAR e RECRIAR banco MySQL com mysqldump"
echo "║   3. Restaurar shared/storage/app/public/* (sobrescreve)"
echo "║"
echo "║ ⚠️  AÇÃO DESTRUTIVA: dados atuais serão substituídos pelos backup"
echo "═══════════════════════════════════════════════════════════════"

if [ "${FORCE:-0}" != "1" ]; then
    echo ""
    read -p "Tem certeza? Digite SIM RESTAURAR: " confirm
    if [ "$confirm" != "SIM RESTAURAR" ]; then
        echo "❌ Cancelado pelo usuário."
        exit 1
    fi
fi

# ── 1. Validar arquivos de backup existem ─────────────────────────
echo ""
echo "▶ Validando backups..."
for f in db-current.sql.gz storage-current.tar.gz env-current.txt; do
    if [ ! -f "$BACKUPS_DIR/$f" ]; then
        echo "❌ Backup ausente: $BACKUPS_DIR/$f"
        echo "   Você precisa de TODOS os 3 arquivos de backup."
        exit 1
    fi
done

# Valida que mysqldump tem tabelas esperadas
if ! zcat "$BACKUPS_DIR/db-current.sql.gz" | head -200 | grep -q "CREATE TABLE.*users"; then
    echo "❌ Backup banco corrompido (sem tabela users)"
    exit 1
fi
echo "  ✅ db-current.sql.gz · íntegro"

if ! tar -tzf "$BACKUPS_DIR/storage-current.tar.gz" >/dev/null 2>&1; then
    echo "❌ Backup storage corrompido"
    exit 1
fi
echo "  ✅ storage-current.tar.gz · íntegro"
echo "  ✅ env-current.txt · presente"

# ── 2. Restaurar .env ──────────────────────────────────────────────
echo ""
echo "▶ Restaurando .env"
cp -f "$BACKUPS_DIR/env-current.txt" "$ENV_TARGET"
chmod 600 "$ENV_TARGET"
echo "  ✅ shared/.env restaurado"

# Carrega credenciais
DB_HOST=$(grep '^DB_HOST=' "$ENV_TARGET" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_DATABASE=$(grep '^DB_DATABASE=' "$ENV_TARGET" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep '^DB_USERNAME=' "$ENV_TARGET" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep '^DB_PASSWORD=' "$ENV_TARGET" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")

# ── 3. Restaurar banco ─────────────────────────────────────────────
echo ""
echo "▶ Restaurando banco MySQL ($DB_DATABASE em $DB_HOST)"
echo "  Atenção: dump tem --add-drop-table, então tabelas atuais serão derrubadas"

if ! zcat "$BACKUPS_DIR/db-current.sql.gz" \
    | mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"; then
    echo "❌ Falha ao restaurar banco"
    exit 1
fi
echo "  ✅ Banco restaurado"

# ── 4. Restaurar storage ───────────────────────────────────────────
echo ""
echo "▶ Restaurando shared/storage/app/public"

# Backup paranoico do atual antes de sobrescrever
if [ -d "$STORAGE_TARGET/app/public" ]; then
    BACKUP_OLD="/tmp/storage-pre-restore-$(date +%s).tar.gz"
    echo "  Backup paranoico do atual em $BACKUP_OLD"
    tar -czf "$BACKUP_OLD" -C "$STORAGE_TARGET" app/public 2>/dev/null || true
fi

mkdir -p "$STORAGE_TARGET"
tar -xzf "$BACKUPS_DIR/storage-current.tar.gz" -C "$STORAGE_TARGET"
echo "  ✅ Storage restaurado"

# ── 5. Symlinks + permissões ───────────────────────────────────────
echo ""
echo "▶ Recriando symlinks e permissões"

# public/storage → ../storage/app/public (usado pelo Laravel)
if [ -d "$RELEASE_CURRENT/public" ]; then
    ln -sfn "$STORAGE_TARGET/app/public" "$RELEASE_CURRENT/public/storage"
    echo "  ✅ symlink public/storage criado"
fi

# Permissões — Laravel precisa de write em storage/ e bootstrap/cache/
chmod -R 775 "$STORAGE_TARGET" || true
echo "  ✅ permissões ajustadas"

# ── 6. Limpa caches ────────────────────────────────────────────────
echo ""
echo "▶ Limpando caches do Laravel"
cd "$RELEASE_CURRENT"
php artisan cache:clear 2>&1 | tail -1 || true
php artisan config:clear 2>&1 | tail -1 || true
php artisan route:clear 2>&1 | tail -1 || true
php artisan view:clear 2>&1 | tail -1 || true
php artisan optimize 2>&1 | tail -3 || true

# ── 7. Smoke test ──────────────────────────────────────────────────
echo ""
echo "▶ Smoke test HTTP"
HTTP_HOME=$(curl -s -o /dev/null -w "%{http_code}" https://fazendamacaybas.com.br/)
HTTP_HEALTH=$(curl -s -o /dev/null -w "%{http_code}" https://fazendamacaybas.com.br/health)
echo "  GET /        → $HTTP_HOME"
echo "  GET /health  → $HTTP_HEALTH"

if [ "$HTTP_HOME" = "200" ] && [ "$HTTP_HEALTH" = "200" ]; then
    echo ""
    echo "═══════════════════════════════════════════════════════════════"
    echo "║ ✅ RESTORE COMPLETO E SISTEMA NO AR"
    echo "║"
    echo "║ Próximos passos:"
    echo "║   1. Fazer login em https://app.fazendamacaybas.com.br/login"
    echo "║   2. Verificar que dados aparecem (animais, transações, etc)"
    echo "║   3. Verificar uploads (avatares, fotos de animais)"
    echo "║   4. Trocar APP_KEY se restore foi após invasão (gera novo .env)"
    echo "═══════════════════════════════════════════════════════════════"
else
    echo ""
    echo "⚠️  HTTP retornou códigos não-200. Verifique logs em:"
    echo "    $RELEASE_CURRENT/storage/logs/laravel.log"
fi
