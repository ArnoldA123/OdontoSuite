<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\Attributes\Test;
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
    /**
     * One row of the credentials table: name | email | `username` | role.
     *
     * The cells tolerate the padding Prettier adds when it aligns a markdown
     * table (`| `elizabet`   |`). The rule this guards is the table structure,
     * never its whitespace.
     */
    private const USER_ROW = '/^\|[^|]*\|\s*([^|]+?)\s*\|\s*`([^`]+)`\s*\|[^|]*\|/m';

    private static function credentialsPath(): string
    {
        return realpath(__DIR__.'/../../..').'/CREDENTIALS.md';
    }

    #[Test]
    public function credentials_md_file_exists(): void
    {
        $this->assertFileExists(self::credentialsPath());
    }

    #[Test]
    public function credentials_md_mentions_default_password(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('password123', $content, 'CREDENTIALS.md must document the default password');
    }

    /**
     * CREDENTIALS.md must document every username the SEEDER creates, and no
     * username it does not.
     *
     * It derives BOTH sides. The seeder is the source of truth, and the
     * comparison runs in both directions: nothing missing, nothing invented.
     */
    #[Test]
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
            'CREDENTIALS.md does not document these seeded usernames: '.implode(', ', $missing)
        );
        $this->assertSame(
            [],
            $invented,
            'CREDENTIALS.md documents usernames the seeder never creates: '.implode(', ', $invented)
        );
    }

    /**
     * Every documented email must be the seeder's email for that username.
     */
    #[Test]
    public function credentials_md_documents_the_seeder_email_for_each_username(): void
    {
        $pairs = self::documentedUsernameEmailPairs();
        $seeded = self::seededUsernameEmails();

        $this->assertNotEmpty($pairs, 'CREDENTIALS.md must document username/email pairs');

        $mismatched = [];
        foreach ($pairs as $username => $email) {
            // A username the seeder does not know is the other test's business.
            if (! isset($seeded[$username])) {
                continue;
            }
            if ($seeded[$username] !== $email) {
                $mismatched[] = "{$username}: doc says {$email}, seeder says {$seeded[$username]}";
            }
        }

        $this->assertSame(
            [],
            $mismatched,
            "CREDENTIALS.md emails disagree with the seeder:\n  ".implode("\n  ", $mismatched)
        );
    }

    /** @return string[] */
    private static function documentedUsernames(): array
    {
        preg_match_all(
            self::USER_ROW,
            (string) file_get_contents(self::credentialsPath()),
            $matches
        );

        return $matches[2];
    }

    /** @return array<string, string> username => email */
    private static function documentedUsernameEmailPairs(): array
    {
        preg_match_all(
            self::USER_ROW,
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
        return realpath(__DIR__.'/../../..').'/database/seeders/RoleBasedUsersSeeder.php';
    }

    #[Test]
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

    #[Test]
    public function credentials_md_has_permission_matrix(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('Matriz de Permisos', $content);
        $this->assertMatchesRegularExpression(
            '/^\|\s*Funcionalidad\s*\|/m',
            $content,
            'CREDENTIALS.md must document the permission matrix header'
        );
    }

    #[Test]
    public function credentials_md_has_login_instructions(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('password123', $content);
    }

    #[Test]
    public function credentials_md_documents_exactly_the_nine_demo_users(): void
    {
        $content = file_get_contents(self::credentialsPath());
        // Cuenta filas de tabla que contienen backtick (username en formato `username`).
        // Ejemplo: "| Elizabet Cunia Cruz | elizabet+administrador@test.com | `elizabet` | administrador |"
        preg_match_all(self::USER_ROW, $content, $matches);
        $this->assertCount(9, $matches[0], 'CREDENTIALS.md must document exactly the nine retained demo users');
    }

    #[Test]
    public function credentials_md_does_not_document_the_pruned_test_accounts(): void
    {
        $documented = self::documentedUsernames();

        foreach (['ever', 'odontologo_test', 'implantologo_test', 'tecnico_test', 'asistente_test', 'finanzas_test'] as $pruned) {
            $this->assertNotContains(
                $pruned,
                $documented,
                "CREDENTIALS.md must not document the pruned account `{$pruned}`."
            );
        }
    }

    #[Test]
    public function credentials_md_documents_the_cleaned_demo_names(): void
    {
        $content = file_get_contents(self::credentialsPath());

        $this->assertStringContainsString('Gerencia OdontoSuite', $content);
        $this->assertStringContainsString('Rosa Quispe Flores', $content);
        $this->assertStringNotContainsString('Admin Test', $content);
        $this->assertStringNotContainsString('Recepcionista Test', $content);
    }

    #[Test]
    public function credentials_md_clarifies_login_field_is_username(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $this->assertStringContainsString('username', $content);
        $this->assertStringContainsString('NO el email', $content);
    }
}
