// Plan #13 (issue #369, U6) — the one write path that does not go through a
// product door.
//
// The approve, reject, PDF and edit states need a quotation in `sent`/`approved`,
// and the SPA has no door to those states (U6 L5). This module places them with a
// `php artisan tinker` child process:
//
//   * the first statement asserts the connection answers `odontosuite_audit` and
//     throws otherwise, so the development database can never be written by a
//     stray `.env`;
//   * the scripts re-read every row and refuse any whose `notes` do not carry the
//     `AUDIT-U6` marker, so a seeded quotation is never flipped.
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
    // A guarded script that refuses is evidence; the child's own words are the
    // only way to know which statement threw.
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

const pieceScript = marker => `
$marker = '${marker}';
$pieces = App\\Models\\DentalPiece::query()
    ->where('is_active', true)
    ->whereHas('toothSurfaces')
    ->orderBy('id')
    ->limit(1)
    ->get(['id', 'fdi_number', 'name']);

if ($pieces->isEmpty()) {
    throw new RuntimeException('no active dental piece with surfaces: the site of a catalog line cannot be declared');
}

$piece = $pieces->first();

echo json_encode([
    'database' => $database,
    'marker' => $marker,
    'piece' => [
        'id' => $piece->id,
        'fdi' => $piece->fdi_number,
        'name' => $piece->name,
    ],
]);
`

const statusScript = (marker, writes) => `
$writes = json_decode('${JSON.stringify(writes)}', true);
$marker = '${marker}';
$applied = [];

foreach ($writes as $write) {
    $quotation = App\\Models\\Quotation::find($write['id']);

    if ($quotation === null) {
        throw new RuntimeException('quotation '.$write['id'].' does not exist');
    }

    if (!str_starts_with((string) $quotation->notes, $marker)) {
        throw new RuntimeException('quotation '.$write['id'].' is not an audit row');
    }

    $from = $quotation->status;
    $quotation->status = $write['status'];
    $quotation->save();

    if ($write['status'] === 'approved') {
        App\\Models\\QuotationApproval::updateOrCreate(
            ['quotation_id' => $quotation->id],
            [
                'patient_id' => $quotation->patient_id,
                'approval_status' => 'approved',
                'approval_date' => now(),
                'approval_method' => 'manual',
                'patient_notes' => $marker.' fixture approval',
            ]
        );
    }

    $applied[] = [
        'id' => $quotation->id,
        'from' => $from,
        'to' => $quotation->status,
    ];
}

echo json_encode(['database' => $database, 'applied' => $applied]);
`
export { php, pieceScript, statusScript }
