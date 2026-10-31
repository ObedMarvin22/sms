#!/usr/bin/env bash
#
# One-command release from Obed's machine (Git Bash on Windows works). Builds the front-end
# files, uploads them, runs scripts/deploy.sh on the server, then checks the site answers.
#
#   DEPLOY_HOST=cpaneluser@server.web-hosting.com SITE_URL=https://orangetree.example bash scripts/release.sh
#
# Optional: DEPLOY_PORT (Namecheap shared hosting uses 21098), DEPLOY_PATH (default: app, i.e. ~/app).
# Put the three settings in scripts/release.env (not committed) to avoid typing them.

set -euo pipefail

main() {
    local here
    here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ -f "$here/release.env" ]]; then
        # shellcheck disable=SC1091
        source "$here/release.env"
    fi

    local host="${DEPLOY_HOST:?Set DEPLOY_HOST, e.g. cpaneluser@server.web-hosting.com}"
    local site="${SITE_URL:?Set SITE_URL, e.g. https://orangetree.example}"
    local port="${DEPLOY_PORT:-21098}"
    local path="${DEPLOY_PATH:-app}"

    cd "$here/.."

    # Release exactly what is on GitHub's main: committed, pushed, nothing extra.
    if [[ "$(git rev-parse --abbrev-ref HEAD)" != "main" ]]; then
        echo "Switch to main first (git checkout main)." >&2
        exit 1
    fi
    if [[ -n "$(git status --porcelain)" ]]; then
        echo "You have uncommitted changes. Commit or stash them first." >&2
        exit 1
    fi
    git fetch --quiet origin main
    if [[ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]]; then
        echo "Your main is not the same as origin/main. Pull or push first." >&2
        exit 1
    fi

    echo "Building front-end files…"
    npm ci --no-audit --no-fund
    npm run build

    echo "Uploading public/build to $host…"
    ssh -p "$port" "$host" "rm -rf '$path/storage/release/build' && mkdir -p '$path/storage/release'"
    scp -q -P "$port" -r public/build "$host:$path/storage/release/build"

    echo "Running deploy.sh on the server…"
    ssh -p "$port" "$host" "bash '$path/scripts/deploy.sh'"

    echo "Checking $site/up…"
    local status
    status="$(curl -s -o /dev/null -w '%{http_code}' "$site/up" || true)"
    if [[ "$status" != "200" ]]; then
        echo "The site answered $status instead of 200. Check it now; roll back with:" >&2
        echo "  ssh -p $port $host \"bash '$path/scripts/deploy.sh' --rollback\"" >&2
        exit 1
    fi

    echo "Released $(git rev-parse --short HEAD). $site is up."
}

main "$@"
exit
