// Plan #13 (issue #370, U7) — the one write path that does not go through a
// product door.
//
// Without `OPENAI_API_KEY` every analysis the API creates ends in `failed`
// (`processAnalysis` catches the provider error and stores it in
// `raw_response`). A citation only accepts an analysis that is `completed` and
// reviewed (`AiAnalysisCitationService`), so the citation and the review of a
// result cannot be exercised with the seed alone. This module promotes the
// session's own analysis rows through a `php artisan tinker` child process:
//
//   * the first statement asserts the connection answers `odontosuite_audit`
//     and throws otherwise, so the development database can never be written by
//     a stray `.env`;
//   * the script re-reads the patient behind every row and refuses any whose
//     `patients.notes` do not carry the `AUDIT-U7` marker, so a seeded or
//     foreign analysis is never flipped.
//
// The caller declares the result as fixture state, never as product behaviour.

import { execFileSync } from 'node:child_process'

const PHP_ENV = Object.freeze({
  APP_ENV: 'local',
  DB_DATABASE: 'odontosuite_audit',
  BROADCAST_CONNECTION: 'null',
  MAIL_MAILER: 'log'
})

const GUARD = `
$database = DB::connection()->getDatabaseName();
if ($database !== 'odontosuite_audit') {
    throw new RuntimeException('refusing to touch database "'.$database.'": the audit writes only odontosuite_audit');
}
`

const php = script => {
  let output

  try {
    output = execFileSync('php', ['artisan', 'tinker', '--execute', `${GUARD}${script}`], {
      cwd: process.cwd(),
      encoding: 'utf8',
      timeout: 90000,
      env: { ...process.env, ...PHP_ENV }
    })
  } catch (thrown) {
    const stderr = String(thrown?.stderr ?? '').trim()
    const stdout = String(thrown?.stdout ?? '').trim()
    throw new Error(
      `tinker exited ${thrown?.status ?? '?'}: ${(stderr || stdout || thrown?.message || '').slice(0, 600)}`
    )
  }

  const line = output
    .split('\n')
    .map(row => row.trim())
    .filter(row => row.startsWith('{') || row.startsWith('['))
    .at(-1)

  if (!line) throw new Error(`tinker produced no JSON: ${output.slice(0, 400)}`)

  return JSON.parse(line)
}

/**
 * Promotes one analysis to a finished, reviewed-looking result.
 *
 * Only `status`, the provider-side result columns and `model_used` are written;
 * `reviewed`/`reviewed_by` stay untouched so the review door is exercised by
 * the session itself (`POST /api/ai-analysis/{id}/review`).
 */
const promoteScript = (marker, writes) => `
$marker = '${marker}';
$writes = json_decode('${JSON.stringify(writes)}', true);
$applied = [];

foreach ($writes as $write) {
    $analysis = App\\Models\\AiImageAnalysis::with('patient')->find($write['id']);

    if ($analysis === null) {
        throw new RuntimeException('analysis '.$write['id'].' does not exist');
    }

    $notes = (string) ($analysis->patient?->notes ?? '');

    if (!str_starts_with($notes, $marker)) {
        throw new RuntimeException('analysis '.$write['id'].' does not belong to an audit patient');
    }

    if ($analysis->reviewed) {
        throw new RuntimeException('analysis '.$write['id'].' is already reviewed');
    }

    $from = $analysis->status;

    $analysis->status = 'completed';
    $analysis->findings = $write['findings'];
    $analysis->recommendations = $write['recommendations'];
    $analysis->confidence_score = $write['confidence_score'];
    $analysis->raw_response = $write['raw_response'];
    $analysis->tokens_used = $write['tokens_used'];
    $analysis->estimated_cost = $write['estimated_cost'];
    $analysis->model_used = $write['model_used'];
    $analysis->save();

    $applied[] = [
        'id' => $analysis->id,
        'from' => $from,
        'to' => $analysis->status,
        'patient_id' => $analysis->patient_id,
    ];
}

echo json_encode(['database' => $database, 'marker' => $marker, 'applied' => $applied]);
`

export { php, promoteScript }
