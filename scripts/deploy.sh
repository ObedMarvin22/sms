#!/usr/bin/env bash
#
# Releases the app on the server (docs/DEPLOYMENT.md, "Releasing"). Run on the server:
#
#   bash ~/app/scripts/deploy.sh             # release origin/main
#   bash ~/app/scripts/deploy.sh --rollback  # go back to the previous release (code and assets only)
#
# Normally started by scripts/release.sh on Obed's machine, which first uploads the freshly built
# front-end files to storage/release/build (the server has no Node).
#
# Everything is inside main(): bash reads the whole function before running it, so the
# `git pull` below may safely replace this very file.

set -euo pipefail

main() {
    local app_dir="${APP_DIR:-$HOME/app}"
    local php="${PHP_BIN:-php}"
    local composer="${COMPOSER_BIN:-composer}"
    local new_build="$app_dir/storage/release/build"
    local mode="${1:-release}"

    cd "$app_dir"

    if [[ "$mode" == "--rollback" ]]; then
        rollback "$php" "$composer" "$app_dir"
        return
    fi

    # 1. Check the server's settings while the site is still up (errors stop here, nothing changed).
    #    The first release that brings app:preflight cannot run it yet.
    if "$php" artisan list --raw 2>/dev/null | grep -q '^app:preflight'; then
        "$php" artisan app:preflight
    fi

    local previous
    previous="$(git rev-parse HEAD)"

    # 2. Maintenance mode. The secret lets you look around while it is down:
    #    https://<domain>/<secret> sets a cookie that bypasses the maintenance page.
    local secret
    secret="release-$(date +%s)"
    "$php" artisan down --retry=60 --secret="$secret"
    # Recorded before anything changes, so --rollback also works after a failed release.
    echo "$previous" > .last-release
    trap 'on_failure' ERR
    echo "Site is down for maintenance. Bypass: <APP_URL>/$secret"

    # 3. Code and dependencies.
    git pull --ff-only origin main
    "$composer" install --no-dev --optimize-autoloader --no-interaction

    # 4. Database. Migrations only ever add (CLAUDE.md); they are never rolled back by this script.
    "$php" artisan migrate --force

    # 5. Front-end files uploaded by release.sh, swapped in only now that migrations succeeded.
    #    The old ones are kept for --rollback.
    if [[ -d "$new_build" ]]; then
        rm -rf public/build.previous
        if [[ -d public/build ]]; then
            mv public/build public/build.previous
        fi
        mv "$new_build" public/build
    else
        echo "No new front-end build uploaded; keeping public/build as it is."
    fi

    # 6. Caches, queue, back up.
    "$php" artisan optimize
    "$php" artisan queue:restart
    trap - ERR
    "$php" artisan up

    echo "Released $(git rev-parse --short HEAD) (previous: ${previous:0:7}; roll back with --rollback)."
}

on_failure() {
    echo >&2
    echo "RELEASE FAILED. The site is still in maintenance mode." >&2
    echo "Either fix the problem and run this script again," >&2
    echo "or go back to the previous release with: bash scripts/deploy.sh --rollback" >&2
}

rollback() {
    local php="$1" composer="$2" app_dir="$3"

    if [[ ! -s .last-release ]]; then
        echo "No previous release recorded in $app_dir/.last-release." >&2
        exit 1
    fi
    local target
    target="$(cat .last-release)"

    echo "Rolling back from $(git rev-parse --short HEAD) to ${target:0:7}."
    echo "Database migrations are NOT undone: the old code runs on the newer database."
    "$php" artisan down --retry=60

    # The server's checkout only follows origin/main, so moving it back loses nothing.
    git reset --hard "$target"
    "$composer" install --no-dev --optimize-autoloader --no-interaction

    if [[ -d public/build.previous ]]; then
        rm -rf public/build.rolled-back
        if [[ -d public/build ]]; then
            mv public/build public/build.rolled-back
        fi
        mv public/build.previous public/build
    fi

    "$php" artisan optimize
    "$php" artisan queue:restart
    rm -f .last-release # one step back only; release again to move forward
    "$php" artisan up
    echo "Rolled back to ${target:0:7}."
}

main "$@"
exit
