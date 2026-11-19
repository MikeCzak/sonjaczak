#!/usr/bin/env bash
# Uploads an encrypted database dump to the Hetzner Storage Box and keeps the last 14 days plus the 1st of each month
# for 12 months (docs/planung/backups-ohne-mac.md, same retention as docs/planung/backups.md).
#
#   sonjaczak-backup-push          daily: dump (via sonjaczak-backup), check, upload, rotate; mail on failure
#   sonjaczak-backup-push status   monthly: mail with the newest backup and the number of backups (file names only)
#
# Runs as root from cron. Uses SFTP on port 22 only (no shell on the Storage Box). The dump is already encrypted with
# age on this server; the private key is not here. Snapshots on the Storage Box protect against deletion with this
# script's key (plan, goal 3). Installed manually as /usr/local/sbin/sonjaczak-backup-push; see docs/deployment.md S13.
set -euo pipefail

CONFIG=${SONJACZAK_PUSH_CONFIG:-/srv/sonjaczak/backup-push.env}
# shellcheck source=/dev/null
. "$CONFIG"    # STORAGE_USER, STORAGE_HOST, MAIL_TO; optional overrides of the defaults below

STORAGE_PORT=${STORAGE_PORT:-22}
STORAGE_DIR=${STORAGE_DIR:-.}    # relative to the login directory; a sub-account starts in its own directory
SSH_KEY=${SSH_KEY:-/root/.ssh/storagebox_ed25519}
KNOWN_HOSTS=${KNOWN_HOSTS:-/root/.ssh/known_hosts_storagebox}
BACKUP_CMD=${BACKUP_CMD:-/usr/local/sbin/sonjaczak-backup}
PHP_CONTAINER=${PHP_CONTAINER:-sonjaczak-php-1}
MAIL_FROM=${MAIL_FROM:-anfrage@sonjaczak.com}
KEEP_DAYS=14
KEEP_MONTHLY_DAYS=366
STALE_DAYS=1
NAME_PATTERN='^[0-9]{4}-[0-9]{2}-[0-9]{2}\.dump\.age$'

log() { echo "$(date '+%F %T') $*" >&2; }

# Same way as the token reminders (docs/deployment.md S6). If the container is down, the error stays in the journal.
send_mail() {
    docker exec "$PHP_CONTAINER" bin/console mailer:test "$MAIL_TO" --from "$MAIL_FROM" \
        --subject "$1" --body "$2" >/dev/null || log "Mail failed: $1"
}

# Runs SFTP commands from stdin. -b: stop at the first failing command (unless prefixed with '-') and exit non-zero.
# BatchMode: never ask for a password. StrictHostKeyChecking=yes: only the fingerprint stored in KNOWN_HOSTS.
sftp_batch() {
    sftp -q -b - -P "$STORAGE_PORT" -i "$SSH_KEY" \
        -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile="$KNOWN_HOSTS" \
        "$STORAGE_USER@$STORAGE_HOST:$STORAGE_DIR"
}

# Backup file names on the Storage Box, sorted (oldest first). sftp echoes the command itself; grep drops it.
# Fails if the Storage Box is unreachable (an empty list would look like "no backups").
list_backups() {
    local out
    out=$(echo 'ls -1' | sftp_batch) || return 1
    printf '%s\n' "$out" | grep -E "$NAME_PATTERN" | sort || true
}

# Days since the date in a file name like 2026-11-14.dump.age (GNU date, Ubuntu)
age_in_days() {
    echo $(( ($(date +%s) - $(date -d "${1%%.*}" +%s)) / 86400 ))
}

status() {
    local files newest count
    if ! files=$(list_backups); then
        send_mail "WARNUNG: Storage Box nicht erreichbar" "Liste der Sicherungen auf $STORAGE_HOST nicht abrufbar."
        exit 1
    fi
    count=$(printf '%s' "$files" | grep -c . || true)
    newest=$(printf '%s\n' "$files" | tail -n 1)
    if [ "$count" -eq 0 ]; then
        send_mail "WARNUNG: Keine Sicherungen in der Storage Box" "Auf $STORAGE_HOST liegt keine Sicherung."
    elif [ "$(age_in_days "$newest")" -gt "$STALE_DAYS" ]; then
        send_mail "WARNUNG: Letzte Sicherung vom ${newest%%.*}" \
            "Die neueste Sicherung ist älter als $STALE_DAYS Tag(e). Anzahl: $count. Älteste: $(printf '%s\n' "$files" | head -n 1)"
    else
        send_mail "Sicherungen vorhanden: neueste vom ${newest%%.*}" \
            "Anzahl: $count. Älteste: $(printf '%s\n' "$files" | head -n 1). Neueste: $newest"
    fi
}

push() {
    local dumpfile target size remote_size files file days
    # Unexpected errors (set -e) also send a mail; -E passes the trap into functions
    set -E
    trap 'fail "Unerwarteter Fehler in Zeile $LINENO"' ERR
    # Global, not local: the EXIT trap runs after push() has returned
    workdir=$(mktemp -d /var/tmp/sonjaczak-backup.XXXXXX)
    trap 'rm -rf "$workdir"' EXIT
    dumpfile="$workdir/dump.age"
    target="$(date +%F).dump.age"

    # pipefail inside sonjaczak-backup: a failed pg_dump is a non-zero exit here
    if ! "$BACKUP_CMD" >"$dumpfile"; then
        fail "Dump fehlgeschlagen"
    fi
    # A failed dump could still be a valid, empty age file; check the header like bin/backup-pull
    if [ "$(head -c 21 "$dumpfile")" != 'age-encryption.org/v1' ]; then
        fail "Keine age-Datei"
    fi
    size=$(stat -c %s "$dumpfile")

    # Upload under a temporary name, then rename: a half-uploaded file never carries a backup name.
    # '-rm': a second run on the same day replaces the earlier file. On the first run of a day sftp prints
    # "No such file" for it and carries on; that line in the journal is expected.
    if ! sftp_batch >/dev/null <<EOF
put $dumpfile .incoming-$target
-rm $target
rename .incoming-$target $target
EOF
    then
        fail "Hochladen fehlgeschlagen"
    fi

    # Size check on the Storage Box (ls -l: 5th column is the size; skip the echoed "sftp> ls -l ..." line)
    remote_size=$(echo "ls -l $target" | sftp_batch | awk -v f="$target" '$1 != "sftp>" && $NF == f { print $5 }')
    if [ "$remote_size" != "$size" ]; then
        fail "Größe stimmt nicht (lokal $size, Storage Box ${remote_size:-?})"
    fi
    log "Uploaded $target ($size bytes)"

    # Rotation only after a successful upload, so failures never delete anything
    files=$(list_backups) || fail "Liste der Sicherungen nicht abrufbar"
    for file in $files; do
        days=$(age_in_days "$file")
        if [ "$days" -le "$KEEP_DAYS" ]; then
            continue
        fi
        if [ "${file:8:2}" = '01' ] && [ "$days" -le "$KEEP_MONTHLY_DAYS" ]; then
            continue
        fi
        echo "rm $file" | sftp_batch >/dev/null || fail "Löschen von $file fehlgeschlagen"
        log "Deleted $file"
    done
}

fail() {
    trap - ERR
    log "$1"
    send_mail "Sicherung fehlgeschlagen: $1" \
        "sonjaczak-backup-push am $(date '+%F %T'): $1. Details: journalctl -u cron bzw. von Hand starten."
    exit 1
}

case "${1:-push}" in
    push) push ;;
    status) status ;;
    *) echo "Usage: $0 [push|status]" >&2; exit 2 ;;
esac
