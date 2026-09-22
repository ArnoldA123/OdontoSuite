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
#   raw-<date>-<time>/            every raw output of that run, unedited
#
# One raw directory per run, not one per day. A single directory was reused, so a
# run that refused the MySQL half still listed the previous run's mysql.txt as its
# own raw output, and a reader could conclude the suite had run when it had not.
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
# Two things the artifact must never be wrong about:
#   * The data-loss guard reads the application database name the way the
#     framework does. When the run would use a live engine that holds the
#     application database, and that name cannot be read, the MySQL half is
#     refused instead of run. `--explain-database-guard [ENV_FILE]` prints the
#     decision so it can be tested rather than trusted.
#   * DB_PASSWORD is masked in the recorded command. Every other counter keeps
#     its command verbatim; this one field does not, on purpose, because the
#     artifact is written to disk and quoted into documents.
#
# Some counters are lower bounds rather than totals, and their names say so. A
# gate that aborts part way through has not measured the rest: Prettier stops at
# the first file whose parser is missing, so `prettier_warned_before_abort` is
# the files it reported up to that point, not every file it would reformat.
# COMPLETE says no required step was skipped; it is not a statement that the
# gates pass, and several of them are red.
#
# The MySQL half needs a container engine (see docker-compose.yml). Without one
# it falls back to a reachable local engine, labels the substitution with the
# engine's real version, and never claims MySQL 8.0 for a different engine.

set -uo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
cd "$ROOT" || exit 2

STAMP=$(date +%Y-%m-%d)
OUT_DIR=".atl/qa-evidence/audit"
RAW_DIR="$OUT_DIR/raw-$STAMP-$(date +%H%M%S)"
ART_MD="$OUT_DIR/baseline-$STAMP.md"
ART_JSON="$OUT_DIR/baseline-$STAMP.json"
TEST_DB="odontosuite_test"

# Counters whose value is a number; everything else stays a string.
NUMERIC_KEYS="routes_total,routes_api,controllers_api,models,migrations,seeders,js_modules,test_files,eslint_errors,eslint_warnings,eslint_files,prettier_exit,prettier_warned_before_abort,prettier_parse_error,pint_exit,pint_files,pint_issues,build_exit,unit_tests,unit_passed,unit_failed,mysql_tests,mysql_passed,mysql_failed,changed_paths,untracked_paths"

usage() {
  cat <<'USAGE'
scripts/audit/baseline.sh — axis A1 of plan #12

  bash scripts/audit/baseline.sh                     capture and write the artifact
  bash scripts/audit/baseline.sh --check FILE        re-measure and diff against FILE
  bash scripts/audit/baseline.sh --explain-database-guard [ENV_FILE]
                                                     print the data-loss guard decision
  bash scripts/audit/baseline.sh --mask-secrets      mask DB_PASSWORD on stdin (diagnostic)
  bash scripts/audit/baseline.sh --read-counters FILE
                                                     print the counters block FILE carries (diagnostic)

Exit codes: 0 complete, 1 a claimed counter differs, 2 usage error, 3 partial.
USAGE
}

strip_ansi() {
  sed -E $'s/\x1b\\[[0-9;]*[A-Za-z]//g'
}

# env_value <key> [file] — reads one key the way phpdotenv does: whitespace around
# the separator tolerated, CRLF tolerated, a matched pair of quotes stripped and,
# for an unquoted value, everything from the first `#` treated as a comment.
# Prints nothing when the file, the key or the value cannot be read, which the
# caller must treat as unknown rather than as a default. BASELINE_ENV_FILE
# overrides the path; that seam is what makes the data-loss guard testable
# without touching the real .env.
#
# The first implementation grepped for `^DB_DATABASE=` and cut on `=`. A quoted
# value, a CRLF line ending, spaces around the separator or an absent key all
# slipped through it, and `DB_DATABASE="odontosuite_test"` then named a database
# the suite would wipe. A review of the fix found the trimmed version still kept
# an inline comment, which phpdotenv drops: `DB_DATABASE=odontosuite_test # test`
# compared unequal to the test database name, the guard said proceed, and the
# suite would have wiped the application database. The comment cut below is that
# finding's fix.
#
# Escapes inside a double-quoted value are not decoded. The only value this
# decision has to recognise is the test database's own name, which contains none.
env_value() {
  local key=$1 file=${2:-${BASELINE_ENV_FILE:-.env}} line value
  [ -f "$file" ] || return 0
  line=$(grep -E "^[[:space:]]*${key}[[:space:]]*=" "$file" 2>/dev/null | head -1)
  [ -n "$line" ] || return 0
  value=${line#*=}
  value=${value%$'\r'}
  value=${value#"${value%%[![:space:]]*}"}

  case "$value" in
    \"*)
      # Between the quotes everything is literal, '#' included, and only what
      # follows the closing quote is a comment. An unterminated quote yields
      # nothing, which the guard reads as unknown.
      value=${value#\"}
      case "$value" in
        *\"*) value=${value%%\"*} ;;
        *) value="" ;;
      esac
      ;;
    \'*)
      value=${value#\'}
      case "$value" in
        *\'*) value=${value%%\'*} ;;
        *) value="" ;;
      esac
      ;;
    *)
      # Unquoted: the value ends at the first '#', with or without whitespace
      # before it, and the tail is trimmed away.
      value=${value%%#*}
      value=${value%"${value##*[![:space:]]}"}
      ;;
  esac

  printf '%s' "$value"
}

# The artifact is written to disk and quoted into documents, so a password does
# not belong in it. This is the one field where the recorded command stops being
# verbatim. The secret runs from `DB_PASSWORD=` to the next `KEY=` assignment or
# to the end of the line: the recorded command is `$*`, where quoting is gone,
# so a password with a space spans several words and masking to the first space
# printed the rest (`DB_PASSWORD=*** bar`). A password that itself contains
# ` WORD=` still ends the mask early; that shape cannot be recovered from `$*`
# and is noted rather than solved here.
mask_secrets() {
  local line tail out word
  while IFS= read -r line || [ -n "${line:-}" ]; do
    tail=$line
    out=""
    while [[ $tail == *"DB_PASSWORD="* ]]; do
      out+="${tail%%DB_PASSWORD=*}DB_PASSWORD=***"
      tail="${tail#*DB_PASSWORD=}"
      while [[ $tail == " "* ]]; do tail="${tail# }"; done
      while [ -n "$tail" ]; do
        word="${tail%% *}"
        case "$word" in
          [A-Z_][A-Z0-9_]*=*) break ;;
        esac
        if [ "$tail" = "$word" ]; then
          tail=""
        else
          tail="${tail#* }"
        fi
      done
      if [ -n "$tail" ]; then out+=" "; fi
    done
    out+="$tail"
    printf '%s\n' "$out"
  done
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
      const blocks = [...text.matchAll(/```json\s*\n([\s\S]*?)```/g)].map((m) => m[1])
      if (blocks.length === 0) {
        console.error("no fenced json counters block found in " + process.argv[1])
        process.exit(3)
      }
      // A document may carry other ```json blocks (examples, configs). The
      // counters block is the one whose object holds `counters`: the first
      // block won before, so a document with an unrelated block on top was
      // read as a foreign object and every comparison below was noise.
      let chosen = null
      for (const block of blocks) {
        try {
          const parsed = JSON.parse(block)
          if (parsed && typeof parsed === "object" && parsed.counters && typeof parsed.counters === "object") {
            chosen = block
            break
          }
        } catch (error) { /* not JSON, keep looking */ }
      }
      if (chosen === null && blocks.length === 1) {
        // A single block stays readable even without the wrapper, which keeps
        // documents quoting bare counters working.
        try {
          JSON.parse(blocks[0])
          chosen = blocks[0]
        } catch (error) { /* falls through to the error below */ }
      }
      if (chosen === null) {
        console.error("no fenced json counters block found in " + process.argv[1])
        process.exit(3)
      }
      process.stdout.write(chosen)
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
  # Strip the escapes before counting, or every count is a false zero. The name
  # says "before abort" because the run stops at the first file it cannot parse:
  # this is a lower bound, never "every file Prettier would reformat".
  record prettier_warned_before_abort "$(strip_ansi <"$GATE_DIR/prettier.txt" | grep -c '^\[warn\]')" "$LAST_CMD  # [warn] lines reported before the run aborts"
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
  # The command that actually ran the suite, overrides included. Recorded per
  # counter: the literal `php artisan test --configuration=phpunit.mysql.xml`
  # would name a run that targets the pinned 3307, not the engine that produced
  # these figures.
  local MYSQL_CMD="php artisan test --configuration=phpunit.mysql.xml (not run)"
  local env_file=${BASELINE_ENV_FILE:-.env}
  local decision
  decision=$(database_guard_decision "$env_file" | grep -E '^decision=' | cut -d'=' -f2-)

  # Plan #12 §6: the audit never runs migrate:fresh against the application
  # database, and this suite targets $TEST_DB. When either source names the test
  # database the application's data may live there: refuse, whichever engine is
  # used.
  if [ "$decision" = "refuse:application-database-is-test-database" ]; then
    record mysql_engine "refused" "database_guard_decision $env_file"
    record mysql_status "refused-app-database-is-test-database" "database_guard_decision $env_file"
    PARTIAL_REASON="mysql suite refused: an env-file key or an exported value names $TEST_DB as the application database"
    return
  fi

  # A connection URL that is present and whose database cannot be read means the
  # application's database cannot be proven different: refuse, whichever engine is
  # used. The run itself is pinned below, so this refusal protects the reading, not
  # the run.
  if [ "$decision" = "refuse:unreadable-connection-url" ]; then
    record mysql_engine "refused" "database_guard_decision $env_file"
    record mysql_status "refused-unreadable-connection-url" "database_guard_decision $env_file"
    PARTIAL_REASON="mysql suite refused: a DB_URL is set whose database cannot be read"
    return
  fi

  if docker info >/dev/null 2>&1; then
    run_gate mysql_compose_up docker compose up -d --wait mysql
    if [ "$GATE_EXIT" -eq 0 ]; then
      engine="MySQL 8.0 (compose service, labeled by image mysql:8.0)"
      # Pinned even here: an exported DB_DATABASE would point the run at a
      # database the ephemeral container never creates. Nothing is at risk in a
      # container, but a figure produced by a failed connection is a false one.
      #
      # DB_URL is emptied as well: a URL beats the explicit keys inside the
      # framework, measured on this project, so an inherited one would redirect
      # the pinned run.
      run_gate mysql env "DB_URL=" "DB_DATABASE=$TEST_DB" php artisan test --configuration=phpunit.mysql.xml
      MYSQL_CMD=$(printf '%s' "$LAST_CMD" | mask_secrets)
      status=$([ "$GATE_EXIT" -eq 0 ] && echo ok || echo failed)
    else
      status="skipped-compose-up-failed"
      PARTIAL_REASON="docker compose up failed, see $GATE_DIR/mysql_compose_up.txt"
    fi
    run_gate mysql_compose_down docker compose down
  else
    # Fallback: no container engine, so the suite would run on the developer's
    # live engine, which is where the application database lives. Without its
    # name from either source the two cannot be proven different, and the suite
    # runs migrate:fresh. A container holds no application data, so this refusal
    # is scoped to here.
    if [ "$decision" = "refuse:cannot-verify-application-database" ]; then
      record mysql_engine "refused" "database_guard_decision $env_file"
      record mysql_status "refused-cannot-verify-application-database" "database_guard_decision $env_file"
      PARTIAL_REASON="mysql suite refused: cannot read DB_DATABASE from $env_file and none is exported"
      return
    fi

    # Any refusal this path has not already handled still refuses: the guard
    # answers with a `refuse:` code precisely when it cannot prove the two
    # databases different, and running the suite here would wipe a live
    # application database. Fail closed on the prefix, not on a list of codes.
    case "$decision" in
      refuse:*)
        record mysql_engine "refused" "database_guard_decision $env_file"
        record mysql_status "refused-${decision#refuse:}" "database_guard_decision $env_file"
        PARTIAL_REASON="mysql suite refused: $decision"
        return
        ;;
    esac

    # Keep the engine the env file points at, but label it with its real version
    # and never call it MySQL 8.0.
    local host port user pass version
    host=$(env_value DB_HOST "$env_file")
    port=$(env_value DB_PORT "$env_file")
    user=$(env_value DB_USERNAME "$env_file")
    pass=$(env_value DB_PASSWORD "$env_file")
    version=$(DB_HOST="$host" DB_PORT="$port" DB_USERNAME="$user" DB_PASSWORD="$pass" DB_DATABASE="$TEST_DB" \
      php -r 'try{$c=new mysqli(getenv("DB_HOST"),getenv("DB_USERNAME"),getenv("DB_PASSWORD"),getenv("DB_DATABASE"),(int)getenv("DB_PORT"));echo $c->server_info;}catch(Throwable $e){echo "";}' 2>/dev/null)
    if [ -n "$version" ]; then
      engine="$version (local engine on $host:$port, not the CI container)"
      # `env` exports the overrides to the child process; a bare VAR=value in
      # front of a shell function would not reach `php`. DB_URL is emptied so the
      # run's connection resolves to the pinned database: a URL beats the explicit
      # keys, measured on this project, and an inherited one would redirect it.
      run_gate mysql env "DB_HOST=$host" "DB_PORT=$port" "DB_USERNAME=$user" "DB_PASSWORD=$pass" \
        "DB_URL=" "DB_DATABASE=$TEST_DB" php artisan test --configuration=phpunit.mysql.xml
      MYSQL_CMD=$(printf '%s' "$LAST_CMD" | mask_secrets)
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
    record mysql_tests "$mysql_tests" "$MYSQL_CMD  # $mysql_line"
    record mysql_passed "$mysql_passed" "$MYSQL_CMD"
    record mysql_failed "$mysql_failed" "$MYSQL_CMD"
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
    echo
    echo "\`COMPLETE\` means no required step was skipped. It is not a verdict on the"
    echo "gates: several rows below are red, and a red gate is a measurement, not a failure"
    echo "of this script. A counter whose name says \`before_abort\` is a lower bound."
    echo
    echo "One field is not verbatim: \`DB_PASSWORD\` in a recorded command is masked,"
    echo "because this file is written to disk and quoted into documents."
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
  # BASELINE_CHECK_STUB points at a ready-made counters.txt and skips the live
  # measurement, which is what makes --check testable without running every gate.
  if [ -n "${BASELINE_CHECK_STUB:-}" ]; then
    cp "$BASELINE_CHECK_STUB" "$check_dir/counters.txt" || exit 2
  else
    measure "$check_dir/counters.txt"
  fi

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
    const label = (value) => (value === "" ? "(unmeasured)" : value)
    for (const key of keys) {
      const a = claimed[key]
      const b = measured[key]
      // An empty counter was never measured: a gate that fails leaves its
      // record empty. Two runs that both failed to measure prove nothing, so
      // an empty side never matches and the guard fails closed instead of
      // passing without measuring.
      if (a === "" || b === "") {
        differences.push({ key, claimed: label(a), measured: label(b) })
      } else if (a !== b) differences.push({ key, claimed: a, measured: b })
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

# database_guard_decision <env_file> — the data-loss rule, in one place, so the
# rule that is tested is the rule that runs: measure_mysql() consumes this output
# and --explain-database-guard prints it.
#
# The question is one: is the database the suite will wipe also the database the
# application lives in? Two sources can answer it, and an exported DB_DATABASE
# beats the file inside the framework because Dotenv is immutable. Naming the
# test database in *either* source is enough to refuse: the cost of refusing is a
# suite run, and the cost of the other mistake is data.
# lower <string> — compares database identifiers the way the engine compares
# them. MySQL and MariaDB on Windows default to lower_case_table_names=1, measured
# as 1 on the MariaDB this project develops against, so `ODONTOSUITE_TEST` and
# `odontosuite_test` are one schema and the suite would wipe either spelling. On
# an engine that is case-sensitive the guard then refuses a run it could have
# allowed, which is the side to lose: a refusal costs a test run, the opposite
# mistake costs the database.
lower() {
  printf '%s' "$1" | tr '[:upper:]' '[:lower:]'
}

# url_database <url> — the database a connection URL selects. Prints it and exits
# 0; exits 3 when there is no URL, and 4 when the URL is present but carries no
# database this parse can read, which the guard treats as unknown rather than as
# absent. `parse_url`, which the framework uses, keeps the database in the path,
# a query string follows it, and a URL with no path selects no database at all.
#
# This reads the MySQL-family shapes the runner uses. A URL for another driver
# would be read as a name that is not the test database, which decides the same
# way the framework decides it: the URL does not select the database the suite
# wipes.
url_database() {
  local url=$1 candidate
  [ -n "$url" ] || return 3
  candidate=${url%%\?*}
  candidate=${candidate%%\#*}
  case "$candidate" in
    *://*/*) candidate=${candidate##*/} ;;
    *) return 4 ;;
  esac
  [ -n "$candidate" ] || return 4
  printf '%s' "$candidate"
}

# framework_database <env_file> — asks the framework which database the `mysql`
# connection resolves to, instead of inferring it from the env file's text.
#
# The answer is the one the run gets: an exported value beats the file (Dotenv is
# immutable inside the framework) and a URL beats the explicit keys
# (config/database.php maps `url` => env('DB_URL')), and the connection's own
# getConfig('database') is read after both are applied. DB_DATABASE and DB_URL
# absent from both sources are exported empty, so a repository `.env` cannot
# answer behind the env file's back. DB_CONNECTION is deliberately left alone: an
# empty one makes the framework refuse to boot ("Database connection [] not
# configured"), and the connection under test is named explicitly anyway.
#
# Prints the resolved name, or FRAMEWORK_UNAVAILABLE when the probe cannot run:
# the caller refuses on that, because a guard that cannot see the resolution has
# proven nothing.
#
# BASELINE_FRAMEWORK_PROBE replaces the probe with an arbitrary shell command (the
# same seam idea as BASELINE_ENV_FILE), so the tests can prove the decision
# follows the framework's answer even when it contradicts every static source.
FRAMEWORK_UNAVAILABLE='__framework-resolution-unavailable__'

framework_database() {
  local env_file=$1 key value out status
  local probe_env=()
  local probe_cmd=${BASELINE_FRAMEWORK_PROBE:-}

  for key in DB_DATABASE DB_URL; do
    value=$(printenv "$key" 2>/dev/null || true)
    if [ -z "$value" ] && [ -n "$env_file" ]; then
      value=$(env_value "$key" "$env_file")
    fi
    probe_env+=("$key=$value")
  done

  if [ -n "$probe_cmd" ]; then
    out=$(cd "$ROOT" && env "${probe_env[@]}" sh -c "$probe_cmd" 2>/dev/null)
  else
    out=$(cd "$ROOT" && env "${probe_env[@]}" php artisan tinker --execute='echo DB::connection("mysql")->getConfig("database");' 2>/dev/null)
  fi
  status=$?

  if [ "$status" -ne 0 ]; then
    printf '%s' "$FRAMEWORK_UNAVAILABLE"
    return 0
  fi

  printf '%s' "$(printf '%s' "$out" | tail -n 1 | tr -d '\r')"
}

database_guard_decision() {
  local env_file=$1
  local app_db_env_file app_db_exported url_env_file url_exported
  local url_db_env_file="" url_db_exported=""
  local framework_db
  app_db_env_file=$(env_value DB_DATABASE "$env_file")
  app_db_exported=$(printenv DB_DATABASE 2>/dev/null || true)
  url_env_file=$(env_value DB_URL "$env_file")
  url_exported=$(printenv DB_URL 2>/dev/null || true)

  # A URL that is present and whose database cannot be read is left empty rather
  # than ignored, and the refusal below treats it as unknown: the reviewer's
  # remedy for the source this guard used to miss.
  local url_unreadable=0
  if [ -n "$url_env_file" ]; then
    url_db_env_file=$(url_database "$url_env_file") || { url_db_env_file=""; url_unreadable=1; }
  fi
  if [ -n "$url_exported" ]; then
    url_db_exported=$(url_database "$url_exported") || { url_db_exported=""; url_unreadable=1; }
  fi

  echo "app_database_env_file=${app_db_env_file:-(unset)}"
  echo "app_database_exported=${app_db_exported:-(unset)}"
  echo "app_database_url_env_file=${url_db_env_file:-(unset)}"
  echo "app_database_url_exported=${url_db_exported:-(unset)}"
  # What the framework resolves, in its own order: a URL beats the explicit keys,
  # and an exported value beats the file for each key. Measured on this project:
  # with DB_URL selecting odontosuite_test and DB_DATABASE naming odontosuite, the
  # resolved connection is odontosuite_test.
  echo "app_database=${url_db_exported:-${url_db_env_file:-${app_db_exported:-${app_db_env_file:-(unset)}}}}"

  # The decision comes from the framework's own resolution (issue #41), not from
  # comparing strings: the static sources above name the source when the
  # framework cannot be asked, and stay as the second net below.
  framework_db=$(framework_database "$env_file")
  if [ "$framework_db" = "$FRAMEWORK_UNAVAILABLE" ]; then
    echo "app_database_framework=unavailable"
    echo "decision=refuse:framework-resolution-unavailable"
    return 0
  fi
  echo "app_database_framework=${framework_db:-(empty)}"

  if [ -n "$framework_db" ] && [ "$(lower "$framework_db")" = "$(lower "$TEST_DB")" ]; then
    echo "decision=refuse:application-database-is-test-database"
    return 0
  fi

  if [ "$(lower "$app_db_env_file")" = "$(lower "$TEST_DB")" ] ||
    [ "$(lower "$app_db_exported")" = "$(lower "$TEST_DB")" ] ||
    [ "$(lower "$url_db_env_file")" = "$(lower "$TEST_DB")" ] ||
    [ "$(lower "$url_db_exported")" = "$(lower "$TEST_DB")" ]; then
    echo "decision=refuse:application-database-is-test-database"
    return 0
  fi

  if [ "$url_unreadable" -eq 1 ]; then
    echo "decision=refuse:unreadable-connection-url"
    return 0
  fi

  if [ -z "$app_db_env_file" ] && [ -z "$app_db_exported" ]; then
    echo "decision=refuse:cannot-verify-application-database"
    return 0
  fi

  echo "decision=proceed"
}

# explain_database_guard [env_file] — prints the decision and the sources behind
# it, without running anything. It reports what the *fallback* path decides,
# because that is the path that can reach a live application database; an
# ephemeral container holds no application data and is never refused for an
# unreadable env file. So this answers "would the audit wipe this database?" for
# any env file, which is what makes the guard testable instead of trusted.
explain_database_guard() {
  local env_file=${1:-${BASELINE_ENV_FILE:-.env}}

  echo "env_file=$env_file"
  echo "test_database=$TEST_DB"
  echo "compose_path=proceed"
  database_guard_decision "$env_file"
}

MODE=capture
CHECK_TARGET=""
READ_TARGET=""
GUARD_ENV=""
while [ $# -gt 0 ]; do
  case "$1" in
    --check) MODE=check; CHECK_TARGET=${2:-}; shift 2 ;;
    --mask-secrets) MODE=mask; shift ;;
    --read-counters) MODE=read; READ_TARGET=${2:-}; shift 2 ;;
    --explain-database-guard)
      MODE=guard
      shift
      # The env file is optional, and a following flag is not one.
      if [ $# -gt 0 ] && [ "${1#--}" = "$1" ]; then GUARD_ENV=$1; shift; fi
      ;;
    --help | -h) usage; exit 0 ;;
    *) echo "unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

if [ "$MODE" = check ]; then
  [ -n "$CHECK_TARGET" ] || { echo "--check requires a file" >&2; exit 2; }
  check "$CHECK_TARGET"
elif [ "$MODE" = mask ]; then
  mask_secrets
elif [ "$MODE" = read ]; then
  [ -n "$READ_TARGET" ] || { echo "--read-counters requires a file" >&2; exit 2; }
  [ -f "$READ_TARGET" ] || { echo "no such file: $READ_TARGET" >&2; exit 2; }
  read_fenced_counters "$READ_TARGET"
elif [ "$MODE" = guard ]; then
  explain_database_guard "$GUARD_ENV"
else
  capture
fi
