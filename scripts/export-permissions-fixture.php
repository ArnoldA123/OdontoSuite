<?php

/**
 * Generates the frontend test fixture of role => permissions from the single
 * source of truth (#280). Run after changing App\Authorization\Permissions:
 *
 *   php scripts/export-permissions-fixture.php
 *
 * FrontendPermissionContractTest fails when the committed fixture drifts, so
 * the smoke suite never invents an authorization rule of its own.
 */
require __DIR__.'/../vendor/autoload.php';

$matrix = [];

foreach (App\Authorization\Permissions::ROLES as $role) {
    $matrix[$role] = App\Authorization\Permissions::forRole($role);
}

$path = __DIR__.'/../tests/js/support/role-permissions.json';
$json = json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

if (file_put_contents($path, $json) === false) {
    fwrite(STDERR, "Could not write {$path}\n");
    exit(1);
}

fwrite(STDOUT, "Wrote {$path} (".count($matrix)." roles).\n");
