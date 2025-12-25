#!/usr/bin/env bash
set -euo pipefail

# Dump SQLite DB to a normalized SQL file suitable for git
# Usage: ./bin/dump-db.sh [path/to/db] [path/to/output.sql]

DB=${1:-data/ngw.db}
OUT=${2:-data/ngw.sql}
TMPDIR=$(mktemp -d)
trap 'rm -rf "${TMPDIR}"' EXIT

if [ ! -f "$DB" ]; then
  echo "Database not found at $DB"
  exit 1
fi

echo "Generating normalized dump from $DB -> $OUT"

# Prepare output
: > "$OUT"

# Get list of tables (exclude sqlite internal tables)
TABLES=$(sqlite3 "$DB" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;")

for tbl in $TABLES; do
  echo "-- Table: $tbl" >> "$OUT"
  # Dump CREATE statement
  sqlite3 "$DB" "SELECT sql FROM sqlite_master WHERE type='table' AND name='$tbl';" >> "$OUT"
  echo ";" >> "$OUT"

  # Get column list
  cols=$(sqlite3 "$DB" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | paste -sd',' -)
  # first column (used for ordering if present)
  firstcol=$(echo "$cols" | awk -F',' '{print $1}')

  # Build expression of quotes: quote(col1) || ',' || quote(col2) ...
  expr=""
  IFS=',' read -r -a colarr <<< "$cols"
  for c in "${colarr[@]}"; do
    if [ -z "$expr" ]; then
      expr="quote($c)"
    else
      expr="$expr || ',' || quote($c)"
    fi
  done

  # Generate ordered INSERT statements per row
  if [ -n "$expr" ]; then
    sqlite3 -batch "$DB" "SELECT 'INSERT INTO \"$tbl\" ($cols) VALUES(' || ($expr) || ');' FROM \"$tbl\" $( [ -n "$firstcol" ] && echo "ORDER BY $firstcol" ) ;" >> "$OUT" || true
  fi

  echo "" >> "$OUT"
done

# Normalize timestamps to a stable constant to reduce noisy diffs
# Replace 'YYYY-MM-DD HH:MM:SS' literals inside single quotes with '1970-01-01 00:00:00'
perl -pe "s/'\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}'/'1970-01-01 00:00:00'/g" "$OUT" > "$TMPDIR/out.sql"

# Remove inserts into sqlite_sequence (environment-specific)
perl -ni -e 'print unless /INSERT INTO \"sqlite_sequence\"/' "$TMPDIR/out.sql"

# Move final output
mv "$TMPDIR/out.sql" "$OUT"

echo "Normalized dump written to $OUT"

exit 0
