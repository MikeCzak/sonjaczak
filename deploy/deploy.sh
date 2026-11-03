#!/bin/sh
# Deploys one image version on the production server.
#
# Called by GitHub Actions as the forced command of the deploy user's SSH key
# (authorized_keys: restrict,command="/srv/sonjaczak/deploy.sh"), so the only input is the commit hash in
# SSH_ORIGINAL_COMMAND. For manual rollbacks on the server: sudo -u deploy /srv/sonjaczak/deploy.sh <commit hash>
#
# The compose files are taken from the image itself, so the SSH key cannot be used to run arbitrary containers.
# This script is installed manually on the server (owned by root, not writable by deploy); see docs/deployment.md.
set -eu

APP_DIR=/srv/sonjaczak
# Read by compose.prod.yaml: image = ${IMAGES_PREFIX}app-php-prod:${IMAGE_TAG}
export IMAGES_PREFIX=ghcr.io/mikeczak/sonjaczak/
IMAGE="${IMAGES_PREFIX}app-php-prod"

tag=${SSH_ORIGINAL_COMMAND:-${1:-}}
case "$tag" in
    *[!0-9a-f]* | "")
        echo "Usage: deploy.sh <40 character commit hash>" >&2
        exit 64
        ;;
esac
if [ "${#tag}" -ne 40 ]; then
    echo "Usage: deploy.sh <40 character commit hash>" >&2
    exit 64
fi

# One deployment at a time
exec 9>"$APP_DIR/release/.lock"
flock -n 9 || { echo "Another deployment is running" >&2; exit 75; }

echo "==> Pulling $IMAGE:$tag"
docker pull --quiet "$IMAGE:$tag"

echo "==> Extracting compose files from the image"
container=$(docker create "$IMAGE:$tag")
trap 'docker rm "$container" >/dev/null' EXIT
docker cp "$container:/app/compose.yaml" "$APP_DIR/release/compose.yaml"
docker cp "$container:/app/compose.prod.yaml" "$APP_DIR/release/compose.prod.yaml"

# Secrets and SERVER_NAME come from $APP_DIR/.env (Compose variable interpolation)
compose() {
    docker compose \
        --project-name sonjaczak \
        --project-directory "$APP_DIR" \
        --env-file "$APP_DIR/.env" \
        -f "$APP_DIR/release/compose.yaml" \
        -f "$APP_DIR/release/compose.prod.yaml" \
        "$@"
}

export IMAGE_TAG="$tag"

echo "==> Starting containers"
compose up --detach --wait --no-build --remove-orphans

echo "==> Running database migrations"
compose exec -T php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "$tag" >"$APP_DIR/release/CURRENT"

echo "==> Removing unused images"
docker image prune --all --force >/dev/null

echo "==> Deployed $tag"
