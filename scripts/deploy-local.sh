#!/usr/bin/env bash
#
# deploy-local.sh — Deploy manual do PC para o Hostinger.
#
# Substituto do pipeline GitHub Actions enquanto o repositório está
# sem minutos de CI disponíveis. Reaproveita a mesma estrutura de
# releases atômicas (releases/<id>/, shared/, symlink current) e o mesmo
# activate.sh que roda no servidor — só que dispara daqui.
#
# Pré-requisitos locais: git-bash (ou WSL), npm, ssh, scp, tar.
# Pré-requisitos servidor: composer, php 8.2+, rsync (tudo presente).
#
# Uso:
#   bash scripts/deploy-local.sh
#
# Flags via env:
#   SKIP_BUILD=1    — pula `npm ci` + `npm run build` (reusa public/build/ atual)
#   DRY_RUN=1       — empacota o tarball mas não faz scp/ssh
#   VERBOSE=1       — adiciona -v ao ssh/scp

set -euo pipefail

# ── Config (sobreponível via env) ──────────────────────────────────
SSH_KEY="${SSH_KEY:-$HOME/.ssh/macaybas_deploy}"
SSH_HOST="${SSH_HOST:-147.93.14.208}"
SSH_PORT="${SSH_PORT:-65002}"
SSH_USER="${SSH_USER:-u931382046}"
DEPLOY_BASE="${DEPLOY_BASE:-/home/u931382046/domains/fazendamacaybas.com.br}"

RELEASE_ID="$(date -u +'%Y%m%d%H%M%S')"
ARTIFACT_NAME="release-${RELEASE_ID}.tar.gz"
ARTIFACT_LOCAL="/tmp/${ARTIFACT_NAME}"
ARTIFACT_REMOTE="${DEPLOY_BASE}/artifacts/${ARTIFACT_NAME}"

SSH_OPTS=(-i "${SSH_KEY}" -o StrictHostKeyChecking=no -o ConnectTimeout=15)
[ "${VERBOSE:-0}" = "1" ] && SSH_OPTS+=(-v)

echo "╔══════════════════════════════════════════════════════════════"
echo "║ Deploy manual → Hostinger"
echo "║ Release ID   : ${RELEASE_ID}"
echo "║ Target       : ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
echo "║ Deploy base  : ${DEPLOY_BASE}"
echo "║ SSH key      : ${SSH_KEY}"
echo "╚══════════════════════════════════════════════════════════════"

# ── 0. Pré-checagem da chave SSH ───────────────────────────────────
if [ ! -f "${SSH_KEY}" ]; then
    echo "❌ Chave SSH não encontrada em ${SSH_KEY}"
    echo "   Defina SSH_KEY=/caminho/da/chave ou gere: ssh-keygen -t ed25519 -f ${SSH_KEY}"
    exit 1
fi

echo "▶ Testando conexão SSH..."
ssh -p "${SSH_PORT}" "${SSH_OPTS[@]}" -o BatchMode=yes \
    "${SSH_USER}@${SSH_HOST}" 'echo "✅ SSH OK"; id; pwd' \
    || { echo "❌ SSH falhou — verifique a chave em authorized_keys do Hostinger"; exit 1; }

# ── 1. Build frontend ──────────────────────────────────────────────
if [ "${SKIP_BUILD:-0}" = "1" ]; then
    echo "▶ [SKIP_BUILD=1] pulando npm ci/build — usando public/build/ atual"
    if [ ! -d "public/build" ]; then
        echo "❌ SKIP_BUILD=1 mas public/build/ não existe. Rode sem SKIP_BUILD."
        exit 1
    fi
else
    echo "▶ npm ci"
    npm ci
    echo "▶ npm run build"
    npm run build
fi

# ── 2. Tarball ─────────────────────────────────────────────────────
# Exclui: dev files, caches, env, node_modules, vendor (será instalado
# no servidor via composer). Inclui: scripts/activate.sh (atualiza o do
# shared/scripts em cada deploy).
echo "▶ Criando tarball ${ARTIFACT_LOCAL}"
tar --exclude='./.git' \
    --exclude='./.github' \
    --exclude='./node_modules' \
    --exclude='./tests' \
    --exclude='./vendor' \
    --exclude='./storage/logs/*' \
    --exclude='./storage/framework/cache/data/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*' \
    --exclude='./.env*' \
    --exclude='./CREDENCIAIS*' \
    --exclude='./docs' \
    --exclude='./qa-evidence' \
    --exclude='./qa-ux' \
    --exclude='./screenshots' \
    --exclude='./qa-output' \
    --exclude='./tmp' \
    --exclude='./*.tmp' \
    -czf "${ARTIFACT_LOCAL}" .

SIZE=$(du -h "${ARTIFACT_LOCAL}" | cut -f1)
echo "   Tarball: ${SIZE}"

if [ "${DRY_RUN:-0}" = "1" ]; then
    echo "▶ [DRY_RUN=1] parando antes do upload. Tarball preservado em ${ARTIFACT_LOCAL}"
    exit 0
fi

# ── 3. Sincroniza activate.sh atual para o servidor ────────────────
# (garante que mudanças no activate.sh são aplicadas antes do próximo run)
echo "▶ Sincronizando scripts/activate.sh para shared/scripts/"
scp -P "${SSH_PORT}" "${SSH_OPTS[@]}" \
    scripts/activate.sh \
    "${SSH_USER}@${SSH_HOST}:${DEPLOY_BASE}/shared/scripts/activate.sh"

# ── 4. Upload do artefato ──────────────────────────────────────────
echo "▶ Subindo ${ARTIFACT_NAME}"
scp -P "${SSH_PORT}" "${SSH_OPTS[@]}" \
    "${ARTIFACT_LOCAL}" \
    "${SSH_USER}@${SSH_HOST}:${ARTIFACT_REMOTE}"

# ── 5. Ativa release no servidor ───────────────────────────────────
echo "▶ Ativando release remotamente (activate.sh)"
ssh -p "${SSH_PORT}" "${SSH_OPTS[@]}" \
    "${SSH_USER}@${SSH_HOST}" \
    "RELEASE_ID=${RELEASE_ID} DEPLOY_BASE=${DEPLOY_BASE} bash ${DEPLOY_BASE}/shared/scripts/activate.sh"

# ── 6. Cleanup local ───────────────────────────────────────────────
rm -f "${ARTIFACT_LOCAL}"

# ── 7. Smoke test ──────────────────────────────────────────────────
echo ""
echo "▶ Smoke test HTTP"
curl -fsS -o /dev/null -w "GET /          → %{http_code}\n" https://fazendamacaybas.com.br/ || true
curl -fsS -o /dev/null -w "GET /health    → %{http_code}\n" https://fazendamacaybas.com.br/health || true

echo ""
echo "✅ Deploy ${RELEASE_ID} concluído."
echo "   Site: https://fazendamacaybas.com.br/"
