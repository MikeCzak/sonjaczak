#!/usr/bin/env bash
# Writes an encrypted dump of the production database to stdout (docs/planung/backups.md).
#
# Started by the Mac over SSH: the key of the user dbbackup may only run this script (authorized_keys:
# restrict,command="sudo /usr/local/sbin/sonjaczak-backup"), and sudo allows dbbackup nothing else. Takes no input.
# Encrypted with the public age key in RECIPIENTS, so the server can create backups but never read them.
# Installed manually on the server as /usr/local/sbin/sonjaczak-backup (owned by root); see docs/deployment.md S11.
set -euo pipefail

RECIPIENTS=/srv/sonjaczak/backup-recipient.txt
CONTAINER=sonjaczak-database-1

# Custom format for pg_restore. Sessions and password links are kept as empty tables: sign-ins do not belong in
# copies. pipefail makes a failing pg_dump fail the whole command instead of producing an encrypted empty file.
docker exec "$CONTAINER" sh -c 'exec pg_dump --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --format=custom \
    --exclude-table-data=sessions --exclude-table-data=reset_password_request' \
    | age --encrypt --recipients-file "$RECIPIENTS"
