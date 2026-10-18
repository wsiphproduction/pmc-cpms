#!/bin/bash
#
# Pull-based deploy for the cPanel host, which has no SSH for GitHub to push
# through. A cPanel cron runs this every minute; it does nothing unless CI
# has moved the `production` branch since the last deploy.
#
#   * * * * * /bin/bash /home/ph1lsagac0m/cpms.philsaga.com/scripts/deploy.sh >> /home/ph1lsagac0m/cpms.philsaga.com/storage/logs/deploy.log 2>&1
#
# Everything lives in main(), which bash parses in full before running, so
# the merge below can safely replace this file mid-run.

main() {
    set -euo pipefail

    APP_ROOT="${APP_ROOT:-/home/ph1lsagac0m/cpms.philsaga.com}"
    PHP="${PHP:-/usr/local/bin/ea-php82}"
    COMPOSER="${COMPOSER:-/opt/cpanel/composer/bin/composer}"
    BRANCH="${BRANCH:-production}"

    cd "$APP_ROOT"

    # One deploy at a time — a slow composer install can outlast the minute.
    exec 9>"storage/framework/deploy.lock"
    flock -n 9 || exit 0

    # Fail rather than hang the cron waiting for a password prompt.
    export GIT_TERMINAL_PROMPT=0
    # Explicit refspec: the host's git (1.8.x) won't otherwise move
    # origin/$BRANCH on a single-branch fetch — it only writes FETCH_HEAD.
    git fetch --quiet origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"

    local current target
    current="$(git rev-parse HEAD)"
    target="$(git rev-parse "origin/$BRANCH")"
    [ "$current" = "$target" ] && exit 0

    log "Deploying ${current:0:7} -> ${target:0:7}"

    "$PHP" artisan down --retry=30 || true
    trap '"$PHP" artisan up || true; log "Deploy FAILED — site brought back up on whatever code is checked out."' ERR

    # Fast-forward only: if someone edited or committed on the server, stop
    # and leave it for a human instead of clobbering their change.
    git merge --ff-only --quiet "origin/$BRANCH"

    if ! git diff --quiet "$current" "$target" -- composer.lock; then
        log "composer.lock changed — installing PHP dependencies"
        "$PHP" "$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    fi

    "$PHP" artisan migrate --force
    "$PHP" artisan optimize
    # The scheduler's --stop-when-empty workers pick up the new code on
    # their next start; this just stops one that is mid-drain right now.
    "$PHP" artisan queue:restart

    trap - ERR
    "$PHP" artisan up
    log "Deployed ${target:0:7}: $(git log -1 --format=%s)"
}

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

main "$@"
