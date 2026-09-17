#!/usr/bin/env bash
#
# scripts/audit/baseline.sh — axis A1 of plan #12 (docs/mejoras/12-programa-auditoria-integral-2026-08.md)
#
# Captures every vital sign of this repository in one execution, so that no
# figure has to be maintained by hand. Each counter is recorded together with
# the command that produced it: a number without a command is prose.
#
#   bash scripts/audit/baseline.sh                  # capture and write the artifact
#   bash scripts/audit/baseline.sh --check FILE     # re-measure and diff against FILE
#
# Artifacts (gitignored, under .atl/qa-evidence/audit/):
#   baseline-<date>.md            human-readable, holds the counters block
#   baseline-<date>.json          the same counters, machine-readable
#   raw-<date>/                   every raw output, unedited
#
# Any document carrying a fenced ```json block with the same shape as
# baseline-<date>.json can be checked: --check re-measures and reports every
# difference, including a figure that was claimed and is no longer measured.
# That is the property the axis requires: the guard must be able to fail.
#
# Exit codes:
#   0  capture complete, or every claimed counter matched
#   1  --check found at least one difference
#   2  usage or environment error
#   3  capture is PARTIAL: a required step could not run (recorded, never hidden)
#
# The MySQL half needs a container engine (see docker-compose.yml). Without one
# it falls back to a reachable local engine, labels the substitution with the
# engine's real version, and never claims MySQL 8.0 for a different engine.

set -uo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$ROOT" || exit 2

STAMP=$(date +%Y-%m-%d)
OUT_DIR=".atl/qa-evidence/audit"
RAW_DIR="$OUT_DIR/raw-$STAMP"
ART_MD="$OUT_DIR/baseline-$STAMP.md"
ART_JSON="$OUT_DIR/baseline-$STAMP.json"
TEST_DB="odontosuite_test"

# Counters whose value is a number; everything else stays a string.
NUMERIC_KEYS="routes_total,routes_api,controllers_api,models,migrations,seeders,js_modules,test_files,eslint_errors,eslint_warnings,eslint_files,prettier_exit,prettier_flagged,prettier_parse_error,pint_exit,pint_files,pint_issues,build_exit,unit_tests,unit_passed,unit_failed,mysql_tests,mysql_passed,mysql_failed,changed_paths,untracked_paths"

usage() {
  sed -n '3,32p' "${BASH_SOURCE[0]}"
}

strip_ansi() {
  sed -E $'s/\x1b\\[[0-9;]*[A-Za-z]//g'
}

# record <key> <value> <command> — one TSV row per counter.
record() {
  local key=$1 value=$2 command=$3
  value=$(printf '%s' "$value" | tr '\n\t' '  ')
  command=$(printf '%s' "$command" | tr '\n\t' '  ')
  printf '%s\t%s\t%s\n' "$key" "$value" "$command" >>"$PAIRS"
}

# run_gate <raw-name> <command...> — runs a command, keeps its raw output.
# Sets GATE_EXIT. The command is recorded verbatim, so every figure is traceable.
# Writes into GATE_DIR, so a --check run never overwrites a capture's raw output.
run_gate() {
  local raw_name=$1
  shift
  local raw_file="$GATE_DIR/$raw_name.txt"
  "${@}" >"$raw_file" 2>&1
  GATE_EXIT=$?
  LAST_CMD="$*"
}

count_from() {
  printf '%s' "$1" | grep -oE "[0-9]+ $2" | grep -oE '^[0-9]+' | tail -1
}

pairs_to_json() {
  node -e '
    const fs = require("fs")
    const numeric = new Set(process.argv[2].split(","))
    const rows = fs
      .readFileSync(process.argv[1], "utf8")
      .split("\n")
      .filter((line) => line.includes("\t"))
    const counters = {}
    const commands = {}
    for (const row of rows) {
      const [key, value, command] = row.split("\t")
      const isNumber = numeric.has(key) && value !== "" && !Number.isNaN(Number(value))
      counters[key] = isNumber ? Number(value) : value
      commands[key] = command ?? ""
    }
    process.stdout.write(JSON.stringify({ counters, commands }, null, 2) + "\n")
  ' "$1" "$NUMERIC_KEYS"
}

read_fenced_counters() {
  node -e '
    const fs = require("fs")
    const text = fs.readFileSync(process.argv[1], "utf8")
    const trimmed = text.trim()
    if (trimmed.startsWith("{")) process.stdout.write(trimmed)
    else {
      // Non-greedy up to the first closing fence, so a block whose closing
      // braces share the line with the fence is still readable. An earlier
      // generator wrote `}``` ` on one line and this guard could not read the
      // artifact it had just produced.
      const match = text.match(/```json\s*\n([\s\S]*?)```/)
      if (!match) {
        console.error("no fenced json counters block found in " + process.argv[1])
        process.exit(3)
      }
      process.stdout.write(match[1])
    }
  ' "$1"
}

# ---------------------------------------------------------------- measurement

measure() {
  PAIRS="$1"
  GATE_DIR=$(dirname "$1")
  : >"$PAIRS"
  PARTIAL_REASON=""

  # --- revision and tree state
  local sha branch changed untracked
  sha=$(git rev-parse HEAD 2>/dev/null)
  branch=$(git branch --show-current 2>/dev/null)
  changed=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')
  untracked=$(git status --porcelain 2>/dev/null | grep -c '^??')
  record changed_paths "$changed" "git status --porcelain | wc -l"
  record untracked_paths "$untracked" "git status --porcelain | grep -c '^??'"

  # --- structure
  record routes_total "$(php artisan route:list --json 2>/dev/null | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{try{process.stdout.write(String(JSON.parse(s).length))}catch(e){process.stdout.write("")}})')" "php artisan route:list --json | node -e 'JSON.parse(stdin).length'"
  record routes_api "$(php artisan route:list --json 2>/dev/null | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{try{process.stdout.write(String(JSON.parse(s).filter(r=>r.uri.startsWith("api/")).length))}catch(e){process.stdout.write("")}})')" "php artisan route:list --json | node -e 'filter(uri.startsWith(\"api/\")).length'"
  record controllers_api "$(ls app/Http/Controllers/Api/*.php 2>/dev/null | wc -l | tr -d ' ')" "ls app/Http/Controllers/Api/*.php | wc -l"
  record models "$(ls app/Models/*.php 2>/dev/null | wc -l | tr -d ' ')" "ls app/Models/*.php | wc -l"
  record migrations "$(ls database/migrations/*.php 2>/dev/null | wc -l | tr -d ' ')" "ls database/migrations/*.php | wc -l"
  record seeders "$(ls database/seeders/*.php 2>/dev/null | wc -l | tr -d ' ')" "ls database/seeders/*.php | wc -l"
  record js_modules "$(ls -d resources/js/modules/*/ 2>/dev/null | wc -l | tr -d ' ')" "ls -d resources/js/modules/*/ | wc -l"
  record test_files "$(find tests -name '*Test.php' 2>/dev/null | wc -l | tr -d ' ')" "find tests -name '*Test.php' | wc -l"

  # --- gates
  run_gate eslint pnpm exec eslint . --ext .vue,.js,.jsx,.ts,.tsx --format json
  # shellcheck disable=SC2154  # GATE_EXIT is set by run_gate
  local eslint_json eslint_errors eslint_warnings eslint_files
  eslint_json=$(node -e '
    const fs = require("fs")
    const text = fs.readFileSync(process.argv[1], "utf8")
    const start = text.indexOf("[")
    if (start < 0) {
      console.error("eslint produced no json array")
      process.exit(1)
    }
    // The runner may print a banner before the array and a lifecycle footer
    // after it, so the last bracket in the file is not always the array end.
    // Walk back over closing brackets until the slice parses. No string
    // scanning: that needs a quote literal inside a string, which does not
    // survive every layer that edits this file.
    let results = null
    let cursor = text.length
    for (let attempt = 0; attempt < 4 && results === null; attempt += 1) {
      const close = text.lastIndexOf("]", cursor - 1)
      if (close < start) break
      try {
        results = JSON.parse(text.slice(start, close + 1))
      } catch (error) {
        cursor = close
      }
    }
    if (results === null) {
      console.error("eslint json did not parse")
      process.exit(1)
    }
    let errors = 0
    let warnings = 0
    let files = 0
    for (const result of results) {
      errors += result.errorCount
      warnings += result.warningCount
      if (result.errorCount + result.warningCount > 0) files += 1
    }
    process.stdout.write(`${errors} ${warnings} ${files}`)
  ' "$GATE_DIR/eslint.txt" 2>"$GATE_DIR/eslint-parse.err")
  if [ -z "$eslint_json" ]; then
    PARTIAL_REASON="eslint counters could not be parsed, see $GATE_DIR/eslint-parse.err"
  fi
  eslint_errors=$(printf '%s' "$eslint_json" | cut -d' ' -f1)
  eslint_warnings=$(printf '%s' "$eslint_json" | cut -d' ' -f2)
  eslint_files=$(printf '%s' "$eslint_json" | cut -d' ' -f3)
  record eslint_errors "$eslint_errors" "$LAST_CMD  # exit $GATE_EXIT"
  record eslint_warnings "$eslint_warnings" "$LAST_CMD"
  record eslint_files "$eslint_files" "$LAST_CMD"

  run_gate prettier pnpm format:check
  record prettier_exit "$GATE_EXIT" "$LAST_CMD"
  # Prettier colourises the level inside the brackets: `[\e[33mwarn\e[39m] file`.
  # Strip the escapes before counting, or every count is a false zero.
  record prettier_flagged "$(strip_ansi <"$GATE_DIR/prettier.txt" | grep -c '^\[warn\]')" "$LAST_CMD  # count of [warn] lines"
  record prettier_parse_error "$(strip_ansi <"$GATE_DIR/prettier.txt" | grep -c "Couldn't resolve parser")" "$LAST_CMD  # count of parser errors"

  run_gate pint vendor/bin/pint --test
  record pint_exit "$GATE_EXIT" "$LAST_CMD"
  local pint_summary pint_files pint_issues
  pint_summary=$(strip_ansi <"$GATE_DIR/pint.txt" | grep -E '[0-9]+ files, [0-9]+ style issues' | tail -1)
  pint_files=$(printf '%s' "$pint_summary" | grep -oE '[0-9]+ files' | grep -oE '[0-9]+')
  pint_issues=$(printf '%s' "$pint_summary" | grep -oE '[0-9]+ style issues' | grep -oE '[0-9]+')
  record pint_files "$pint_files" "$LAST_CMD  # summary line: $pint_summary"
  record pint_issues "$pint_issues" "$LAST_CMD  # summary line"

  run_gate build pnpm build
  record build_exit "$GATE_EXIT" "$LAST_CMD"

  # --- unit suite (the default runner: SQLite in memory)
  run_gate unit php artisan test --testsuite=Unit
  local unit_line unit_tests unit_passed unit_failed
  unit_line=$(strip_ansi <"$GATE_DIR/unit.txt" | grep -E '^ *Tests:' | tail -1)
  unit_passed=$(count_from "$unit_line" passed)
  unit_failed=$(count_from "$unit_line" failed)
  unit_tests=$(( ${unit_passed:-0} + ${unit_failed:-0} ))
  record unit_tests "$unit_tests" "$LAST_CMD  # $unit_line"
  record unit_passed "$unit_passed" "$LAST_CMD"
  record unit_failed "$unit_failed" "$LAST_CMD"

  # --- mysql suite (phpunit.mysql.xml; engine resolved and labelled below)
  measure_mysql
}

measure_mysql() {
  local engine="unavailable" status="skipped"
  local app_db
  app_db=$(grep -E '^DB_DATABASE=' .env 2>/dev/null | head -1 | cut -d'=' -f2-)

  if [ "$app_db" = "$TEST_DB" ]; then
    # Plan #12 §6: the audit never runs migrate:fresh against the application
    # database. If .env points the application at the test database, running
    # the suite would destroy real data.
    record mysql_engine "refused" "grep DB_DATABASE .env"
    record mysql_status "refused-app-database-is-test-database" "grep DB_DATABASE .env"
    PARTIAL_REASON="mysql suite refused: .env DB_DATABASE equals $TEST_DB"
    return
  fi

  if docker info >/dev/null 2>&1; then
    run_gate mysql_compose_up docker compose up -d --wait mysql
    if [ "$GATE_EXIT" -eq 0 ]; then
      engine="MySQL 8.0 (compose service, labeled by image mysql:8.0)"
      run_gate mysql php artisan test --configuration=phpunit.mysql.xml
      status=$([ "$GATE_EXIT" -eq 0 ] && echo ok || echo failed)
    else
      status="skipped-compose-up-failed"
      PARTIAL_REASON="docker compose up failed, see $GATE_DIR/mysql_compose_up.txt"
    fi
    run_gate mysql_compose_down docker compose down
  else
    # Fallback: no container engine. Use the engine .env already points at, but
    # label it with its real version and never call it MySQL 8.0.
    local host port user pass version
    host=$(grep -E '^DB_HOST=' .env 2>/dev/null | head -1 | cut -d'=' -f2-)
    port=$(grep -E '^DB_PORT=' .env 2>/dev/null | head -1 | cut -d'=' -f2-)
    user=$(grep -E '^DB_USERNAME=' .env 2>/dev/null | head -1 | cut -d'=' -f2-)
    pass=$(grep -E '^DB_PASSWORD=' .env 2>/dev/null | head -1 | cut -d'=' -f2-)
    version=$(DB_HOST="$host" DB_PORT="$port" DB_USERNAME="$user" DB_PASSWORD="$pass" DB_DATABASE="$TEST_DB" \
      php -r 'try{$c=new mysqli(getenv("DB_HOST"),getenv("DB_USERNAME"),getenv("DB_PASSWORD"),getenv("DB_DATABASE"),(int)getenv("DB_PORT"));echo $c->server_info;}catch(Throwable $e){echo "";}' 2>/dev/null)
    if [ -n "$version" ]; then
      engine="$version (local engine on $host:$port, not the CI container)"
      # `env` exports the overrides to the child process; a bare VAR=value in
      # front of a shell function would not reach `php`.
      run_gate mysql env "DB_HOST=$host" "DB_PORT=$port" "DB_USERNAME=$user" "DB_PASSWORD=$pass" \
        "DB_DATABASE=$TEST_DB" php artisan test --configuration=phpunit.mysql.xml
      status=$([ "$GATE_EXIT" -eq 0 ] && echo ok || echo failed)
    else
      status="skipped-no-engine"
      PARTIAL_REASON="no container engine and no reachable local engine at $host:$port"
      : >"$GATE_DIR/mysql.txt"
    fi
  fi

  record mysql_engine "$engine" "docker info; php -r mysqli(...)->server_info"
  record mysql_status "$status" "docker info; php artisan test --configuration=phpunit.mysql.xml"

  if [ -f "$GATE_DIR/mysql.txt" ] && [ -s "$GATE_DIR/mysql.txt" ]; then
    local mysql_line mysql_tests mysql_passed mysql_failed
    mysql_line=$(strip_ansi <"$GATE_DIR/mysql.txt" | grep -E '^ *Tests:' | tail -1)
    mysql_passed=$(count_from "$mysql_line" passed)
    mysql_failed=$(count_from "$mysql_line" failed)
    mysql_tests=$(( ${mysql_passed:-0} + ${mysql_failed:-0} ))
    record mysql_tests "$mysql_tests" "php artisan test --configuration=phpunit.mysql.xml  # $mysql_line"
    record mysql_passed "$mysql_passed" "php artisan test --configuration=phpunit.mysql.xml"
    record mysql_failed "$mysql_failed" "php artisan test --configuration=phpunit.mysql.xml"
  fi
}

# -------------------------------------------------------------------- capture

capture() {
  mkdir -p "$RAW_DIR" || exit 2
  measure "$RAW_DIR/counters.txt"

  local sha branch changed status
  sha=$(git rev-parse HEAD 2>/dev/null)
  branch=$(git branch --show-current 2>/dev/null)
  changed=$(git status --porcelain 2>/dev/null | wc -l | tr -d ' ')
  status=$([ -z "$PARTIAL_REASON" ] && echo COMPLETE || echo PARTIAL)

  pairs_to_json "$RAW_DIR/counters.txt" >"$ART_JSON" || exit 2

  {
    echo "# Audit baseline — $STAMP"
    echo
    echo "Revision: \`$sha\` (branch \`$branch\`)"
    echo "Tree at capture time: $changed changed path(s)"
    echo "Generated by: \`bash scripts/audit/baseline.sh\`"
    echo "Overall status: **$status**"
    [ -n "$PARTIAL_REASON" ] && echo "Unavailable step: $PARTIAL_REASON"
    echo
    echo "## Vital signs"
    echo
    echo "| Counter | Value | Command |"
    echo "| --- | --- | --- |"
    awk -F'\t' '{gsub(/\|/, "\\|", $2); gsub(/\|/, "\\|", $3); printf "| `%s` | %s | `%s` |\n", $1, $2, $3}' "$RAW_DIR/counters.txt"
    echo
    echo "## Raw outputs"
    echo
    # A path contains slashes, so `|` is the delimiter here, not `/`. Directories
    # are skipped: a --check run leaves its own subdirectory behind.
    ls -1p "$RAW_DIR" | grep -v '/$' | sed -E "s|^(.+)$|- \`$RAW_DIR/\1\`|"
    echo
    echo "## Machine-readable counters"
    echo
    echo '```json'
    cat "$ART_JSON"
    echo '```'
  } >"$ART_MD"

  echo "baseline: $ART_MD"
  echo "counters: $ART_JSON"
  echo "status:   $status"
  if [ -n "$PARTIAL_REASON" ]; then
    echo "partial:  $PARTIAL_REASON" >&2
    exit 3
  fi
  exit 0
}

# ---------------------------------------------------------------------- check

check() {
  local target=$1
  [ -f "$target" ] || { echo "no such file: $target" >&2; exit 2; }

  local check_dir="$RAW_DIR/check-$(basename "$target" | tr -c 'A-Za-z0-9._-' '_')"
  mkdir -p "$check_dir" || exit 2
  measure "$check_dir/counters.txt"

  local claimed measured
  claimed=$(read_fenced_counters "$target" 2>/dev/null) || {
    echo "cannot read counters from $target" >&2
    exit 2
  }
  measured=$(pairs_to_json "$check_dir/counters.txt") || exit 2

  node -e '
    const claimedAll = JSON.parse(process.argv[1])
    const measuredAll = JSON.parse(process.argv[2])
    const claimed = claimedAll.counters ?? claimedAll
    const measured = measuredAll.counters ?? measuredAll
    // Tree-state counters describe the working tree at the moment of capture, so
    // a later run can never match them. Comparing them would make the guard fail
    // for a reason that is not a claim about the project, and a guard that always
    // fails is a guard nobody reads.
    const ignored = new Set(["changed_paths", "untracked_paths"])
    const keys = [...new Set([...Object.keys(claimed), ...Object.keys(measured)])]
      .filter((key) => !ignored.has(key))
      .sort()
    const differences = []
    for (const key of keys) {
      const a = claimed[key]
      const b = measured[key]
      if (a !== b) differences.push({ key, claimed: a, measured: b })
    }
    if (differences.length === 0) {
      console.log(`OK: ${keys.length} counter(s) match in ${process.argv[3]}`)
      process.exit(0)
    }
    console.log(`MISMATCH: ${differences.length} of ${keys.length} counter(s) in ${process.argv[3]}`)
    console.log()
    console.log("| Counter | Claimed | Measured |")
    console.log("| --- | --- | --- |")
    for (const d of differences) {
      console.log(`| ${d.key} | ${d.claimed === undefined ? "(absent)" : d.claimed} | ${d.measured === undefined ? "(absent)" : d.measured} |`)
    }
    console.log()
    console.log("Re-measured with: bash scripts/audit/baseline.sh --check")
    process.exit(1)
  ' "$claimed" "$measured" "$target"
}

MODE=capture
CHECK_TARGET=""
while [ $# -gt 0 ]; do
  case "$1" in
    --check) MODE=check; CHECK_TARGET=${2:-}; shift 2 ;;
    --help | -h) usage; exit 0 ;;
    *) echo "unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [ "$MODE" = check ]; then
  [ -n "$CHECK_TARGET" ] || { echo "--check requires a file" >&2; exit 2; }
  check "$CHECK_TARGET"
else
  capture
fi
