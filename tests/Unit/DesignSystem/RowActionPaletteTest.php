<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Row-action palette guard.
 *
 * Five tables (pacientes, profesionales, ambientes, tipos de cita,
 * especialidades) expose the same five row actions. The defect this guard
 * exists for: "Ver ficha" and "Editar" were both painted with the brand accent,
 * so the read action and the write action were indistinguishable. The fix moved
 * the palette into `design-system/rowActionPalette.js` and the guard compares
 * TOKEN NAMES — not class strings — because a class-string comparison passes
 * while two aliased tokens resolve to the same hex.
 *
 * That is not hypothetical: `systemBlue` is a deprecated alias of `accent` in
 * `tokens.js`, so `text-systemBlue-600` and `--color-accent` are two spellings
 * of the same green ramp. Rule (a) therefore fails on the token name, and
 * `test_system_steel_and_the_brand_accent_are_different_colours` pins the
 * measurement that makes the chosen token usable.
 */
class RowActionPaletteTest extends TestCase
{
    private const PALETTE = '/resources/js/design-system/rowActionPalette.js';

    private const TOKENS = '/resources/js/design-system/tokens.js';

    /** @var array<string, string> */
    private const SURFACES = [
        'Pacientes' => '/resources/js/modules/patients/PatientsPage.vue',
        'Profesionales' => '/resources/js/modules/professionals/ProfessionalsPage.vue',
        'Ambientes' => '/resources/js/modules/environments/EnvironmentsPage.vue',
        'Tipos de cita' => '/resources/js/modules/appointment-types/AppointmentTypesPage.vue',
        'Especialidades' => '/resources/js/modules/specialties/SpecialtiesPage.vue',
    ];

    private const ACTION_KEYS = ['view', 'edit', 'archive', 'reactivate', 'delete'];

    /**
     * Which actions each table paints. Especialidades is deactivation-only by
     * domain rule (`procedure_catalog.specialty_id` and `user_specialties`
     * reference the row), so it has no delete action to colour.
     *
     * @var array<string, array<int, string>>
     */
    private const SURFACE_ACTIONS = [
        'Pacientes' => ['view', 'edit', 'delete'],
        'Profesionales' => ['view', 'edit', 'delete'],
        'Ambientes' => ['view', 'edit', 'archive', 'reactivate', 'delete'],
        'Tipos de cita' => ['view', 'edit', 'delete'],
        'Especialidades' => ['view', 'edit', 'archive', 'reactivate'],
    ];

    private static function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 3).$path);
        self::assertNotFalse($source, sprintf('%s must be readable.', $path));

        return $source;
    }

    /**
     * Resolve the palette entries to `key => ['token' => ..., 'classes' => ...]`.
     * The module keeps each class string in a named constant, so the constants
     * are parsed first and the entries are resolved through them.
     *
     * @return array<string, array{token: string, classes: string}>
     */
    private static function palette(): array
    {
        $source = self::read(self::PALETTE);

        preg_match_all('/const\s+([A-Z_]+)\s*=\s*\'([^\']*)\'/', $source, $constants, PREG_SET_ORDER);
        $map = [];
        foreach ($constants as $constant) {
            $map[$constant[1]] = $constant[2];
        }

        preg_match_all(
            '/\b(view|edit|archive|reactivate|delete):\s*Object\.freeze\(\{\s*token:\s*\'([^\']+)\',\s*classes:\s*(\'[^\']*\'|[A-Z_]+)\s*\}\)/',
            $source,
            $entries,
            PREG_SET_ORDER
        );

        $palette = [];
        foreach ($entries as $entry) {
            $raw = $entry[3];
            $classes = str_starts_with($raw, "'") ? trim($raw, "'") : ($map[$raw] ?? '');
            $palette[$entry[1]] = ['token' => $entry[2], 'classes' => $classes];
        }

        self::assertSame(
            self::ACTION_KEYS,
            array_keys($palette),
            sprintf('%s must declare exactly the five contracted actions in order.', self::PALETTE)
        );

        foreach ($palette as $key => $action) {
            self::assertNotSame(
                '',
                $action['classes'],
                sprintf('Action `%s` must resolve to a non-empty class string (constant defined?).', $key)
            );
        }

        return $palette;
    }

    /**
     * The `#actions` slot of a table template. Nested `<template>` tags are
     * counted (icon slots use them), so the block ends at its real closing tag.
     */
    private static function actionsBlock(string $source, string $label): string
    {
        $start = strpos($source, '<template #actions=');
        self::assertNotFalse($start, sprintf('%s must declare a `#actions` slot.', $label));

        $depth = 0;
        $cursor = $start;
        while (true) {
            $nextOpen = strpos($source, '<template', $cursor + 1);
            $nextClose = strpos($source, '</template>', $cursor + 1);
            self::assertNotFalse($nextClose, sprintf('%s `#actions` slot must close.', $label));

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $cursor = $nextOpen;

                continue;
            }

            if ($depth === 0) {
                return substr($source, $start, $nextClose - $start);
            }

            $depth--;
            $cursor = $nextClose;
        }
    }

    /** @return array<string, array{0: string}> */
    public static function surfaceProvider(): array
    {
        $cases = [];
        foreach (self::SURFACES as $label => $path) {
            $cases[$label] = [$label];
        }

        return $cases;
    }

    /**
     * (a) — the read action and the write action must not share the palette.
     * Compared by TOKEN, so an aliased spelling cannot hide the collision.
     */
    public function test_view_and_edit_do_not_share_a_token(): void
    {
        $palette = self::palette();

        $this->assertNotSame(
            $palette['edit']['token'],
            $palette['view']['token'],
            '«Ver ficha» and «Editar» MUST NOT resolve to the same colour token: the read action and the '
                .'primary write action were both painted with the brand accent, which is the defect this '
                .'palette exists to remove.'
        );
    }

    /**
     * (a), second half — `systemBlue` is a deprecated alias of `accent` in
     * `tokens.js`. Naming it as the read action would re-create the collision
     * with a different class string.
     */
    public function test_the_read_action_never_uses_the_deprecated_system_blue_alias(): void
    {
        $palette = self::palette();

        foreach ($palette as $key => $action) {
            $this->assertDoesNotMatchRegularExpression(
                '/systemBlue/',
                $action['token'].' '.$action['classes'],
                sprintf(
                    'Action `%s` MUST NOT use `systemBlue`: it is a deprecated alias of `accent` in '
                        .'design-system/tokens.js, so it resolves to the same green ramp.',
                    $key
                )
            );
        }

        $this->assertStringStartsWith(
            'systemSteel',
            $palette['view']['token'],
            '«Ver ficha» reads without changing state; it takes the informational steel ramp, the hue the '
                .'semantic layer already owns (StatusBadge `info`).'
        );
    }

    /**
     * (c) — a reversible state change is amber; green belongs to reactivation
     * and gray to the neutral surface, so an archive action must not borrow
     * either of them.
     */
    public function test_archive_is_amber_and_never_green_or_gray(): void
    {
        $token = self::palette()['archive']['token'];

        $this->assertMatchesRegularExpression(
            '/^system(Orange|Yellow)-[0-9]+$/',
            $token,
            sprintf('«Archivar»/«Desactivar» must be amber, found `%s`.', $token)
        );

        foreach (['systemGreen', 'systemGray', 'systemSteel', 'accent'] as $forbidden) {
            $this->assertStringStartsNotWith(
                $forbidden,
                $token,
                sprintf('«Archivar»/«Desactivar» must not use `%s`: it is a reversible state change.', $forbidden)
            );
        }
    }

    /**
     * (b) — destruction is red, and only the delete action is.
     */
    public function test_delete_is_red_and_no_other_action_is(): void
    {
        $palette = self::palette();

        $this->assertMatchesRegularExpression(
            '/^systemRed-[0-9]+$/',
            $palette['delete']['token'],
            sprintf('«Eliminar» must carry the red token, found `%s`.', $palette['delete']['token'])
        );

        foreach (['view', 'edit', 'archive', 'reactivate'] as $key) {
            $this->assertStringStartsNotWith(
                'systemRed',
                $palette[$key]['token'],
                sprintf('Action `%s` must not carry the destructive red.', $key)
            );
        }

        $this->assertMatchesRegularExpression(
            '/^systemGreen-[0-9]+$/',
            $palette['reactivate']['token'],
            '«Reactivar»/«Activar» returns the record to service and takes the system green.'
        );
    }

    /**
     * The token name and the class string are two halves of one decision; if
     * they drift the guard above would certify a colour the page does not paint.
     */
    public function test_each_entry_classes_name_their_own_token(): void
    {
        foreach (self::palette() as $key => $action) {
            $needle = $action['token'] === 'accent' ? 'color-accent' : $action['token'];
            $this->assertStringContainsString(
                $needle,
                $action['classes'],
                sprintf(
                    'Action `%s` declares token `%s` but its classes do not paint it (`%s`).',
                    $key,
                    $action['token'],
                    $action['classes']
                )
            );

            $this->assertMatchesRegularExpression(
                '/(?<![\w-])!text-/',
                $action['classes'],
                sprintf(
                    'Action `%s` must win over the primitive with the `!` modifier: `Button.vue` ghost ships '
                        .'`text-theme-secondary`.',
                    $key
                )
            );
        }
    }

    /**
     * The five tables consume the shared palette instead of repeating it, and
     * the actions block carries no colour of its own.
     */
    #[DataProvider('surfaceProvider')]
    public function test_surface_consumes_the_shared_palette(string $label): void
    {
        $source = self::read(self::SURFACES[$label]);
        $block = self::actionsBlock($source, $label);

        $this->assertMatchesRegularExpression(
            '/import\s*\{[^}]*ROW_ACTION_PALETTE[^}]*\}\s*from\s*[\'"]\.\.\/\.\.\/design-system\/rowActionPalette[\'"]/',
            $source,
            sprintf('%s must import the shared row-action palette.', $label)
        );

        foreach (self::SURFACE_ACTIONS[$label] as $key) {
            $this->assertStringContainsString(
                'ROW_ACTION_PALETTE.'.$key.'.classes',
                $block,
                sprintf('%s must paint the `%s` action from the shared palette.', $label, $key)
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])!?text-(system[A-Z]|accent|\[color:)/',
            $block,
            sprintf(
                '%s must not inline a colour in its row actions: the palette is the single definition, and an '
                    .'inline class is how the five tables drifted apart.',
                $label
            )
        );

        $this->assertDoesNotMatchRegularExpression(
            '/#icon-left/',
            $block,
            sprintf('%s row actions are text only — no icon slot (14px/500 labels).', $label)
        );
    }

    /**
     * Contract 2 (d) — a retired record is not an error. Gray, never red.
     */
    #[DataProvider('surfaceProvider')]
    public function test_surface_never_paints_an_inactive_state_as_an_error(string $label): void
    {
        $source = self::read(self::SURFACES[$label]);

        $this->assertDoesNotMatchRegularExpression(
            '/\?\s*\'success\'\s*:\s*\'error\'/',
            $source,
            sprintf(
                '%s must not paint the inactive state with the `error` variant: «Inactivo» is a neutral '
                    .'state, not a failure.',
                $label
            )
        );

        $this->assertDoesNotMatchRegularExpression(
            "/'error'/",
            self::actionsBlock($source, $label),
            sprintf('%s must not carry a red variant inside its row actions.', $label)
        );
    }

    /**
     * Every surface resolves its inactive pill to `neutral`, whether it reads
     * the shared palette or a module-level status vocabulary.
     */
    public function test_every_inactive_pill_resolves_to_neutral(): void
    {
        $patients = self::read(self::SURFACES['Pacientes']);
        $this->assertMatchesRegularExpression(
            '/patientStatusVariant\s*=\s*patient\s*=>\s*[\s\S]{0,160}?ROW_STATUS_VARIANT\.inactive/',
            $patients,
            'Pacientes must derive its inactive pill from the shared neutral variant.'
        );

        $professionals = self::read(self::SURFACES['Profesionales']);
        $this->assertStringContainsString(
            'rowStatusVariant(row.is_active)',
            $professionals,
            'Profesionales must derive its pill from the shared active/inactive vocabulary.'
        );

        $types = self::read(self::SURFACES['Tipos de cita']);
        $this->assertMatchesRegularExpression(
            '/row\.is_active\s*\?\s*\'success\'\s*:\s*\'neutral\'/',
            $types,
            'Tipos de cita must keep the neutral inactive pill.'
        );

        $environments = self::read('/resources/js/modules/environments/environmentStatus.js');
        $this->assertMatchesRegularExpression(
            '/inactive:\s*\'neutral\'/',
            $environments,
            'Ambientes maps `inactive` to the neutral variant.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/inactive:\s*\'error\'/',
            $environments,
            'Ambientes must not map `inactive` to `error`.'
        );

        $specialties = self::read('/resources/js/modules/specialties/specialtyMeta.js');
        $this->assertMatchesRegularExpression(
            '/getStatusVariant\(isActive\)\s*\{[\s\S]{0,120}?\'neutral\'/',
            $specialties,
            'Especialidades maps an inactive row to the neutral variant.'
        );
    }

    /**
     * Contract 3 (e) — the colour dot beside the name is gone. It read as an
     * orphan element; the colour lives in the create/edit form only.
     */
    public function test_the_appointment_type_name_cell_carries_no_colour_dot(): void
    {
        $source = self::read(self::SURFACES['Tipos de cita']);

        $this->assertStringNotContainsString(
            'data-type-swatch',
            $source,
            'Tipos de cita must not render a colour dot: the swatch sat beside the name as an orphan element.'
        );

        $start = strpos($source, '<template #cell-name=');
        $this->assertNotFalse($start, 'Tipos de cita must declare a `#cell-name` slot.');
        $end = strpos($source, '</template>', $start);
        $this->assertNotFalse($end, 'Tipos de cita `#cell-name` slot must close.');
        $cell = substr($source, $start, $end - $start);

        $this->assertDoesNotMatchRegularExpression(
            '/rounded-full|:style="/',
            $cell,
            'The name cell must carry the name and nothing else — no swatch, no inline colour.'
        );

        $this->assertStringContainsString(
            'v-model="editingType.color"',
            $source,
            'The colour still lives in the create/edit form, which is where it is chosen.'
        );
        $this->assertStringContainsString(
            'type="color"',
            $source,
            'The form keeps the colour input.'
        );
    }

    /**
     * The measurement that made `systemSteel` the read action instead of
     * `systemBlue`: the alias and the brand accent are the same ramp.
     */
    public function test_system_steel_and_the_brand_accent_are_different_colours(): void
    {
        $tokens = self::read(self::TOKENS);

        $accent = self::ramp($tokens, 'accent');
        $steel = self::ramp($tokens, 'systemSteel');
        $blue = self::ramp($tokens, 'systemBlue');

        $this->assertNotSame(
            $accent[600] ?? null,
            $steel[600] ?? null,
            '`systemSteel-600` and the brand accent must not resolve to the same hex.'
        );
        $this->assertNotSame(
            $accent[500] ?? null,
            $steel[600] ?? null,
            '`systemSteel-600` must differ from `accent-500` (`--color-accent`).'
        );

        $this->assertSame(
            $accent[600] ?? null,
            $blue[600] ?? null,
            'Expected `systemBlue` to remain the deprecated alias of `accent`: this is why the read action '
                .'cannot use it. If the alias is ever removed, revisit the palette — not the guard.'
        );
    }

    /** @return array<int, string> */
    private static function ramp(string $tokens, string $name): array
    {
        self::assertMatchesRegularExpression(
            '/\n\s+'.$name.':\s*\{(.*?)\n\s+\},/s',
            $tokens,
            sprintf('tokens.js must declare the `%s` ramp.', $name)
        );
        preg_match('/\n\s+'.$name.':\s*\{(.*?)\n\s+\},/s', $tokens, $matches);

        preg_match_all('/(\d+):\s*\'(#[0-9a-f]{6})\'/', $matches[1], $steps, PREG_SET_ORDER);

        $ramp = [];
        foreach ($steps as $step) {
            $ramp[(int) $step[1]] = $step[2];
        }

        return $ramp;
    }
}
