<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Sprint 4 fix (IM-2): valida que CREDENTIALS.md este sincronizado con
 * los usuarios reales que seedea RoleBasedUsersSeeder.
 *
 * Nota: extendemos PHPUnit\Framework\TestCase directamente (no Tests\TestCase)
 * para evitar cargar el framework de Laravel, que requiere un BROADCAST_DRIVER
 * valido. Usamos __DIR__ para resolver rutas absolutas.
 */
class CredentialsDocumentationTest extends TestCase
{
    private static function projectRoot(): string
    {
        return realpath(__DIR__ . '/../../..');
    }

    private static function credentialsPath(): string
    {
        return self::projectRoot() . '/CREDENTIALS.md';
    }

    private static function seederPath(): string
    {
        return self::projectRoot() . '/database/seeders/RoleBasedUsersSeeder.php';
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
    public function credentials_md_documents_all_seeder_usernames(): void
    {
        $usernames = $this->extractUsernamesFromSeeder();
        $content = file_get_contents(self::credentialsPath());

        foreach ($usernames as $username) {
            $this->assertStringContainsString(
                $username,
                $content,
                "CREDENTIALS.md must document the seeder username: {$username}"
            );
        }
    }

    /** @test */
    public function credentials_md_documents_all_seeder_roles(): void
    {
        $roles = $this->extractRolesFromSeeder();
        $content = file_get_contents(self::credentialsPath());

        foreach ($roles as $role) {
            $this->assertStringContainsString(
                $role,
                $content,
                "CREDENTIALS.md must document the seeder role: {$role}"
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
    public function credentials_md_uses_correct_role_names(): void
    {
        $canonicalRoles = ['administrador', 'recepcionista', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'finanzas'];
        $content = file_get_contents(self::credentialsPath());

        foreach ($canonicalRoles as $role) {
            $this->assertStringContainsString(
                $role,
                $content,
                "CREDENTIALS.md must document canonical role: {$role}"
            );
        }
    }

    /**
     * Extrae usernames del array $users en RoleBasedUsersSeeder.
     *
     * @return array<int, string>
     */
    private function extractUsernamesFromSeeder(): array
    {
        $content = file_get_contents(self::seederPath());
        preg_match_all("/'username'\s*=>\s*'([^']+)'/", $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Extrae roles del array $users en RoleBasedUsersSeeder.
     *
     * @return array<int, string>
     */
    private function extractRolesFromSeeder(): array
    {
        $content = file_get_contents(self::seederPath());
        preg_match_all("/'role'\s*=>\s*'([^']+)'/", $content, $matches);
        return array_unique($matches[1] ?? []);
    }
}
