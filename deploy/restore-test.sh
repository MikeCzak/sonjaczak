#!/usr/bin/env bash
# Restores a database dump from stdin into a temporary database, compares the row counts with the live database
# and drops the temporary database again. The live database is only read. Installed on the server as
# /usr/local/sbin/sonjaczak-restore-test (owned by root); see docs/deployment.md S11.
#
# The dump arrives already decrypted from the Mac, which alone has the private key:
#   age -d -i <key> <file> | ssh mike@sonjaczak.com sudo /usr/local/sbin/sonjaczak-restore-test
set -euo pipefail

CONTAINER=sonjaczak-database-1
TEST_DB=app_restore_test

# Runs SQL in the given database and prints the result without headers
query() {
    docker exec "$CONTAINER" sh -c 'exec psql --username="$POSTGRES_USER" --dbname="$1" --no-psqlrc --tuples-only \
        --no-align --command="$2"' sh "$1" "$2"
}

live_db=$(docker exec "$CONTAINER" sh -c 'echo "$POSTGRES_DB"')

docker exec "$CONTAINER" sh -c 'dropdb --username="$POSTGRES_USER" --if-exists "$1" && createdb --username="$POSTGRES_USER" "$1"' sh "$TEST_DB"
trap 'docker exec "$CONTAINER" sh -c "dropdb --username=\"\$POSTGRES_USER\" --if-exists $TEST_DB"' EXIT

echo "==> Restoring into $TEST_DB"
docker exec -i "$CONTAINER" sh -c 'exec pg_restore --username="$POSTGRES_USER" --dbname="$1" --no-owner --exit-on-error' sh "$TEST_DB"

echo "==> Rows per table (live / restored)"
status=0
for table in $(query "$TEST_DB" "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY 1"); do
    live=$(query "$live_db" "SELECT count(*) FROM \"$table\"")
    restored=$(query "$TEST_DB" "SELECT count(*) FROM \"$table\"")
    case "$table" in
        sessions | reset_password_request) note='(not backed up on purpose)' ;;
        *) if [ "$live" = "$restored" ]; then note=ok; else note=DIFFERENT; status=1; fi ;;
    esac
    printf '%-28s %6s / %-6s %s\n' "$table" "$live" "$restored" "$note"
done

echo "==> Dropping $TEST_DB"
exit "$status"
