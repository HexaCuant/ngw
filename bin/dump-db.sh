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

  # Get full column list
  cols=$(sqlite3 "$DB" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | paste -sd',' -)
  # first column (used for ordering if present)
  firstcol=$(echo "$cols" | awk -F',' '{print $1}')

  # Exclude changing metadata columns to keep dumps stable (no noisy diffs)
  EXCLUDE_REGEX='^(created_at|updated_at|last_login|created|updated|timestamp)$'
  # Build filtered columns array
  IFS=',' read -r -a colarr <<< "$cols"
  filtered_cols_arr=()
  for c in "${colarr[@]}"; do
    if [[ "$c" =~ $EXCLUDE_REGEX ]]; then
      continue
    fi
    filtered_cols_arr+=("$c")
  done
  filtered_cols=$(IFS=","; echo "${filtered_cols_arr[*]}")

  # If no non-metadata columns remain, skip INSERT generation for this table
  if [ -z "$filtered_cols" ]; then
    echo "-- Skipping data for $tbl (only metadata columns present)" >> "$OUT"
  else
    # Build expression of quotes for filtered columns
    expr=""
    for c in "${filtered_cols_arr[@]}"; do
      if [ -z "$expr" ]; then
        expr="quote($c)"
      else
        expr="$expr || ',' || quote($c)"
      fi
    done

    # Determine order clause: prefer first filtered column if present
    ordercol=$firstcol
    if ! echo ",${filtered_cols}," | grep -q ",${firstcol},"; then
      ordercol=$(echo "$filtered_cols" | awk -F',' '{print $1}')
    fi

    # Generate ordered INSERT statements per row using filtered columns
    sqlite3 -batch "$DB" "SELECT 'INSERT INTO \"$tbl\" ($filtered_cols) VALUES(' || ($expr) || ');' FROM \"$tbl\" $( [ -n "$ordercol" ] && echo "ORDER BY $ordercol" ) ;" >> "$OUT" || true
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
