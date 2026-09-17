<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Sprint 4 fix (IM-2): valida que CREDENTIALS.md este sincronizado
 * con la BD activa (MySQL). Verifica estructura y contenido.
 *
 * Nota: extendemos PHPUnit\Framework\TestCase directamente (no Tests\TestCase)
 * para evitar cargar el framework de Laravel y broadcasting.
 */
class CredentialsDocumentationTest extends TestCase
{
    private static function credentialsPath(): string
    {
        return realpath(__DIR__ . '/../../..') . '/CREDENTIALS.md';
    }

    /** @test */
    public function credentials_md_file_exists(): void
    {
        $this->assertFileExists(self::credentialsPath());
    }

    /** @test */
    public function credentials_md_mentions_default_password(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('password123', $content, 'CREDENTIALS.md must document the default password');
    }

    /**
     * @test
     *
     * CREDENTIALS.md must document every username the SEEDER creates, and no
     * username it does not.
     *
     * HISTORY: this test used to hardcode `['adm1n', 'ever', 'admin_test']` and
     * assert those strings appeared in the document. `adm1n` exists in neither
     * the seeder nor the database — the list had hardened a ghost into a
     * contract, so CORRECTING the document broke the test. Exactly the defect
     * `tokens_module_hex_literals_match_ios_palette` carried before it was
     * rewritten to assert relationships: the guard was written against the
     * artifact it was meant to police instead of against the source of truth.
     *
     * It now derives BOTH sides. The seeder is the source of truth, and the
     * comparison runs in both directions: nothing missing, nothing invented.
     */
    public function credentials_md_matches_the_seeder_username_list(): void
    {
        $documented = self::documentedUsernames();
        $seeded = self::seededUsernames();

        $this->assertNotEmpty($seeded, 'the seeder must declare usernames or this check means nothing');
        $this->assertNotEmpty($documented, 'CREDENTIALS.md must document usernames');

        $missing = array_values(array_diff($seeded, $documented));
        $invented = array_values(array_diff($documented, $seeded));

        $this->assertSame(
            [],
            $missing,
            'CREDENTIALS.md does not document these seeded usernames: ' . implode(', ', $missing)
        );
        $this->assertSame(
            [],
            $invented,
            'CREDENTIALS.md documents usernames the seeder never creates: ' . implode(', ', $invented)
        );
    }

    /**
     * @test
     *
     * Every documented email must be the seeder's email for that username. The
     * three ghost rows also carried invented placeholder addresses
     * (`admin@x.com`, `rec@x.com`, `odon@x.com`) that nothing ever compared.
     */
    public function credentials_md_documents_the_seeder_email_for_each_username(): void
    {
        $pairs = self::documentedUsernameEmailPairs();
        $seeded = self::seededUsernameEmails();

        $this->assertNotEmpty($pairs, 'CREDENTIALS.md must document username/email pairs');

        $mismatched = [];
        foreach ($pairs as $username => $email) {
            // A username the seeder does not know is the other test's business.
            if (!isset($seeded[$username])) {
                continue;
            }
            if ($seeded[$username] !== $email) {
                $mismatched[] = "{$username}: doc says {$email}, seeder says {$seeded[$username]}";
            }
        }

        $this->assertSame(
            [],
            $mismatched,
            "CREDENTIALS.md emails disagree with the seeder:\n  " . implode("\n  ", $mismatched)
        );
    }

    /** @return string[] */
    private static function documentedUsernames(): array
    {
        preg_match_all(
            '/^\| .+ \| .+ \| `([^`]+)` \| .+ \|/m',
            (string) file_get_contents(self::credentialsPath()),
            $matches
        );

        return $matches[1];
    }

    /** @return array<string, string> username => email */
    private static function documentedUsernameEmailPairs(): array
    {
        preg_match_all(
            '/^\| .+ \| ([^|]+) \| `([^`]+)` \| .+ \|/m',
            (string) file_get_contents(self::credentialsPath()),
            $matches,
            PREG_SET_ORDER
        );

        $pairs = [];
        foreach ($matches as $row) {
            $pairs[trim($row[2])] = trim($row[1]);
        }

        return $pairs;
    }

    /** @return string[] */
    private static function seededUsernames(): array
    {
        preg_match_all(
            "/'username'\s*=>\s*'([^']+)'/",
            (string) file_get_contents(self::seederPath()),
            $matches
        );

        return $matches[1];
    }

    /** @return array<string, string> username => email */
    private static function seededUsernameEmails(): array
    {
        // Each seeded block declares a username and an email; pair them by
        // order so the mapping survives blocks that also set name, role and
        // password between the two.
        preg_match_all(
            "/'username'\s*=>\s*'([^']+)'.*?'email'\s*=>\s*'([^']+)'/s",
            (string) file_get_contents(self::seederPath()),
            $matches,
            PREG_SET_ORDER
        );

        $pairs = [];
        foreach ($matches as $row) {
            $pairs[$row[1]] = $row[2];
        }

        return $pairs;
    }

    private static function seederPath(): string
    {
        return realpath(__DIR__ . '/../../..') . '/database/seeders/RoleBasedUsersSeeder.php';
    }

    /** @test */
    public function credentials_md_documents_all_seven_roles(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $canonicalRoles = ['administrador', 'recepcionista', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'finanzas'];
        foreach ($canonicalRoles as $role) {
            $this->assertStringContainsString(
                $role,
                $content,
                "CREDENTIALS.md must document canonical role: {$role}"
            );
        }
    }

    /** @test */
    public function credentials_md_has_permission_matrix(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('Matriz de Permisos', $content);
        $this->assertStringContainsString('| Funcionalidad |', $content);
    }

    /** @test */
    public function credentials_md_has_login_instructions(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('password123', $content);
    }

    /** @test */
    public function credentials_md_has_at_least_15_user_rows(): void
    {
        $content = file_get_contents(self::credentialsPath());
        // Cuenta filas de tabla que contienen backtick (username en formato `username`).
        // Ejemplo: "| Elizabet Cunia Cruz | admin@x.com | `adm1n` | administrador |"
        preg_match_all('/^\| .+ \| .+ \| `[^`]+` \| .+ \|/m', $content, $matches);
        $this->assertGreaterThanOrEqual(15, count($matches[0]), 'CREDENTIALS.md should document at least 15 users');
    }

    /** @test */
    public function credentials_md_clarifies_login_field_is_username(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('NO el email', $content);
    }
}
