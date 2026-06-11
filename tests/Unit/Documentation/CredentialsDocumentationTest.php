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

    /** @test */
    public function credentials_md_documents_known_admin_usernames(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $knownAdmins = ['adm1n', 'ever', 'admin_test'];
        foreach ($knownAdmins as $username) {
            $this->assertStringContainsString(
                $username,
                $content,
                "CREDENTIALS.md must document admin username: {$username}"
            );
        }
    }

    /** @test */
    public function credentials_md_documents_known_clinical_usernames(): void
    {
        $content = file_get_contents(self::credentialsPath());
        $knownClinical = ['recep1', 'odonto1', 'brenda', 'wilmer', 'sofia', 'azul', 'milagros'];
        foreach ($knownClinical as $username) {
            $this->assertStringContainsString(
                $username,
                $content,
                "CREDENTIALS.md must document clinical username: {$username}"
            );
        }
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
