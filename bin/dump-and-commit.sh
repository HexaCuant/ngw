#!/usr/bin/env bash
set -euo pipefail

# Helper: generate normalized DB dump and optionally commit/push it to git
# Usage: ./bin/dump-and-commit.sh [--commit] [--push] [-m "Commit message"] [db_path] [out_path]

COMMIT=false
PUSH=false
MSG="Update DB dump"

# parse args
while [[ $# -gt 0 ]]; do
  case "$1" in
    --commit)
      COMMIT=true; shift ;;
    --push)
      PUSH=true; shift ;;
    -m)
      if [[ -z ${2:-} ]]; then echo "-m requires a message"; exit 1; fi
      MSG="$2"; shift 2 ;;
    -h|--help)
      echo "Usage: $0 [--commit] [--push] [-m \"Commit message\"] [db_path] [out_path]"; exit 0 ;;
    --)
      shift; break ;;
    *)
      # treat as positional argument
      break ;;
  esac
done

# Positional arguments after options
DB=${1:-data/ngw.db}
OUT=${2:-data/ngw.sql}

# Ensure dump script exists
if [ ! -x "./bin/dump-db.sh" ]; then
  echo "dump-db.sh not found or not executable. Make sure bin/dump-db.sh exists and is executable." >&2
  exit 1
fi

echo "Generating dump from $DB -> $OUT"
./bin/dump-db.sh "$DB" "$OUT"

# Check for changes relative to git index
if git ls-files --error-unmatch "$OUT" >/dev/null 2>&1; then
  if git diff --quiet -- "$OUT"; then
    echo "No changes to $OUT (dump is identical)."
    exit 0
  else
    echo "$OUT changed." 
  fi
else
  echo "$OUT is new to git; it will be added if --commit is used."
fi

if [ "$COMMIT" = true ]; then
  git add "$OUT"
  git commit -m "$MSG"
  echo "Committed $OUT with message: $MSG"
  if [ "$PUSH" = true ]; then
    git push
    echo "Pushed to remote."
  fi
else
  echo "Dry run: not committing. Run with --commit to stage+commit the dump."
fi

exit 0
