<?php

namespace App\Authorization;

/**
 * Single source of truth for authorization (#280).
 *
 * The catalog uses `verb.resource` names. Routes declare a permission
 * (`permission:view.patient`), never a role list; the role matrix below is the
 * only place that knows which role receives which permission. The client does
 * not repeat this rule: `POST /api/auth/login` and `GET /api/auth/me` return
 * the effective permission list and the SPA only checks membership.
 *
 * Roles are the existing `users.role` values. The program vocabulary groups
 * them as: clinico = odontologo + implantologo + tecnico_dental + asistente;
 * laboratorio = tecnico_dental; recepcion = recepcionista.
 */
final class Permissions
{
    public const ROLES = [
        'administrador',
        'recepcionista',
        'odontologo',
        'implantologo',
        'tecnico_dental',
        'asistente',
        'finanzas',
    ];

    // Session and read-only surface shared by every authenticated role.
    public const VIEW_PROFILE = 'view.profile';

    public const VIEW_DASHBOARD = 'view.dashboard';

    public const AUTHORIZE_BROADCASTING = 'authorize.broadcasting';

    public const VIEW_USER = 'view.user';

    public const VIEW_PATIENT = 'view.patient';

    public const EXPORT_PATIENT = 'export.patient';

    public const VIEW_APPOINTMENT_TYPE = 'view.appointment_type';

    public const VIEW_DENTAL_CHAIR = 'view.dental_chair';

    public const VIEW_BRANCH = 'view.branch';

    public const VIEW_PAYMENT_METHOD = 'view.payment_method';

    public const VIEW_PROCEDURE_CATALOG = 'view.procedure_catalog';

    public const VIEW_SPECIALTY = 'view.specialty';

    // Patients.
    public const CREATE_PATIENT = 'create.patient';

    public const UPDATE_PATIENT = 'update.patient';

    public const DELETE_PATIENT = 'delete.patient';

    // Appointments, consultation and reminders.
    public const MANAGE_APPOINTMENT = 'manage.appointment';

    public const UPDATE_APPOINTMENT_STATUS = 'update.appointment_status';

    public const VIEW_CONSULTATION = 'view.consultation';

    public const CHECK_IN_APPOINTMENT = 'check_in.appointment';

    public const ANNOUNCE_PATIENT = 'announce.patient';

    public const START_CONSULTATION = 'start.consultation';

    public const COMPLETE_APPOINTMENT = 'complete.appointment';

    // F2 (#282): the clinical act. Opening and voiding an encounter are
    // clinical acts, so they follow the same clinical-only grant as the
    // consultation close.
    public const CREATE_ENCOUNTER = 'create.encounter';

    public const VOID_ENCOUNTER = 'void.encounter';

    public const CLOSE_ENCOUNTER = 'close.encounter';

    // Reading the act is clinical too: the encounter carries the evolution,
    // findings, materials and amounts of the record.
    public const VIEW_ENCOUNTER = 'view.encounter';

    // F6 (#286): the CIE-10 catalogue the close codes the diagnosis against.
    // Reading it is part of documenting the act, so it follows the clinical
    // grant of the encounter.
    public const VIEW_DIAGNOSIS_CATALOG = 'view.diagnosis_catalog';

    public const VIEW_MY_DAY = 'view.my_day';

    // F4 (#284): continuity between acts. Reading the pending queue is part of
    // the appointment desktop; materialising it belongs to reception and the
    // administrator, and it never writes clinical records.
    public const VIEW_CONTINUITY = 'view.continuity';

    public const MANAGE_CONTINUITY = 'manage.continuity';

    public const VIEW_PRODUCT = 'view.product';

    // #295 (S6): the warehouse. Reading what is on the shelf is clinical and
    // financial work; receiving a purchase or counting a shelf is not, so the
    // write capability keeps its own list.
    public const VIEW_INVENTORY = 'view.inventory';

    public const MANAGE_INVENTORY = 'manage.inventory';

    public const MANAGE_REMINDER = 'manage.reminder';

    public const SEND_REMINDER = 'send.reminder';

    public const MANAGE_REMINDER_TEMPLATE = 'manage.reminder_template';

    // Medical records.
    public const MANAGE_MEDICAL_RECORD = 'manage.medical_record';

    public const MANAGE_EVOLUTION = 'manage.evolution';

    public const MANAGE_ATTACHMENT = 'manage.attachment';

    public const VIEW_MEDICAL_RECORD_STATS = 'view.medical_record_stats';

    // Treatment plans, quotations and specialty records.
    public const MANAGE_TREATMENT_PLAN = 'manage.treatment_plan';

    public const VIEW_QUOTATION = 'view.quotation';

    public const MANAGE_QUOTATION = 'manage.quotation';

    public const MANAGE_SPECIALTY_RECORD = 'manage.specialty_record';

    public const VIEW_DENTAL_PIECE = 'view.dental_piece';

    // Procedure catalog and statistics.
    public const MANAGE_PROCEDURE_CATALOG = 'manage.procedure_catalog';

    public const IMPORT_PROCEDURE_CATALOG = 'import.procedure_catalog';

    public const MANAGE_PROCEDURE_FAVORITE = 'manage.procedure_favorite';

    public const VIEW_PROCEDURE_STATS = 'view.procedure_stats';

    // Cash register and billing.
    public const VIEW_BILLING = 'view.billing';

    public const CREATE_QUOTATION_FROM_APPOINTMENT = 'create.quotation_from_appointment';

    public const VIEW_CASH_REGISTER = 'view.cash_register';

    public const OPEN_CASH_REGISTER = 'open.cash_register';

    public const CLOSE_CASH_REGISTER = 'close.cash_register';

    public const MANAGE_CASH_MOVEMENT = 'manage.cash_movement';

    public const MANAGE_TRANSACTION = 'manage.transaction';

    public const CREATE_TRANSACTION = 'create.transaction';

    public const VOID_TRANSACTION = 'void.transaction';

    public const VIEW_PENDING_PAYMENT = 'view.pending_payment';

    public const PAY_PENDING_PAYMENT = 'pay.pending_payment';

    public const VIEW_CASH_REPORT = 'view.cash_report';

    public const EXPORT_CASH_REPORT = 'export.cash_report';

    public const MANAGE_PAYMENT_METHOD = 'manage.payment_method';

    public const MANAGE_PAYMENT_MERCADOPAGO = 'manage.payment_mercadopago';

    // Administration.
    public const MANAGE_USER = 'manage.user';

    public const VIEW_AUDIT_LOG = 'view.audit_log';

    public const MANAGE_SPECIALTY = 'manage.specialty';

    public const MANAGE_DENTAL_CHAIR = 'manage.dental_chair';

    public const MANAGE_APPOINTMENT_TYPE = 'manage.appointment_type';

    public const MANAGE_BRANCH = 'manage.branch';

    /** #290 S8: the branch tariff agreed for one sede. */
    public const MANAGE_BRANCH_PRICE = 'manage.branch_price';

    // #293 S3: convenios and the tariff agreed with them.
    public const VIEW_PAYER = 'view.payer';

    public const MANAGE_PAYER = 'manage.payer';

    public const MANAGE_PAYER_PRICE = 'manage.payer_price';

    // F3 (#283, S4/S5): administrate the freeze date of a module, and authorise
    // (with a registered reason) a mutation inside it.
    public const MANAGE_ACCOUNTING_LOCK = 'manage.accounting_lock';

    public const OVERRIDE_ACCOUNTING_LOCK = 'override.accounting_lock';

    public const VIEW_REPORT = 'view.report';

    public const EXPORT_REPORT = 'export.report';

    // AI assistance.
    public const VIEW_AI_ANALYSIS = 'view.ai_analysis';

    public const CREATE_AI_ANALYSIS = 'create.ai_analysis';

    public const REVIEW_AI_ANALYSIS = 'review.ai_analysis';

    public const VOID_AI_ANALYSIS = 'void.ai_analysis';

    // Capabilities without a dedicated route: consumed by the SPA and backed by
    // a Policy in the service layer. The catalog test keeps this list honest.
    public const MANAGE_CONFIG = 'manage.config';

    public const MANAGE_CASH_REGISTER = 'manage.cash_register';

    public const APPLY_TRANSACTION_DISCOUNT = 'apply.transaction_discount';

    public const APPLY_LARGE_TRANSACTION_DISCOUNT = 'apply.large_transaction_discount';

    /** @var list<string> */
    public const FRONTEND_ONLY = [
        self::MANAGE_CONFIG,
        self::MANAGE_CASH_REGISTER,
        self::APPLY_TRANSACTION_DISCOUNT,
        self::APPLY_LARGE_TRANSACTION_DISCOUNT,
    ];

    private const ALL_ROLES = self::ROLES;

    private const CLINICAL = ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'];

    private const SPECIALISTS = ['odontologo', 'implantologo', 'tecnico_dental'];

    private const APPOINTMENT_ROLES = [
        'administrador',
        'recepcionista',
        'odontologo',
        'implantologo',
        'tecnico_dental',
        'asistente',
    ];

    private const MEDICAL_ROLES = [
        'administrador',
        'odontologo',
        'implantologo',
        'tecnico_dental',
        'asistente',
    ];

    private const TREATMENT_ROLES = [
        'administrador',
        'odontologo',
        'implantologo',
        'tecnico_dental',
    ];

    /**
     * #296 (spec S2) — the laboratory desktop does not reach the AI module.
     *
     * An analysis is decision support over a diagnosis: it belongs to the
     * three roles that diagnose, not to `TREATMENT_ROLES`, which the clinical
     * record and the treatment plan legitimately share with the laboratory.
     */
    private const AI_ROLES = ['administrador', 'odontologo', 'implantologo'];

    private const CASH_ROLES = ['administrador', 'finanzas', 'recepcionista'];

    private const FINANCE_ROLES = ['administrador', 'finanzas'];

    private const QUOTATION_READERS = [
        'administrador',
        'finanzas',
        'odontologo',
        'implantologo',
        'recepcionista',
    ];

    private const QUOTATION_WRITERS = [
        'administrador',
        'finanzas',
        'odontologo',
        'implantologo',
    ];

    /**
     * permission => roles that hold it. This is the whole authorization matrix.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        self::VIEW_PROFILE => self::ALL_ROLES,
        self::VIEW_DASHBOARD => self::ALL_ROLES,
        self::AUTHORIZE_BROADCASTING => self::ALL_ROLES,
        self::VIEW_USER => self::ALL_ROLES,
        self::VIEW_PATIENT => self::ALL_ROLES,
        self::EXPORT_PATIENT => self::ALL_ROLES,
        self::VIEW_APPOINTMENT_TYPE => self::ALL_ROLES,
        self::VIEW_DENTAL_CHAIR => self::ALL_ROLES,
        self::VIEW_BRANCH => self::ALL_ROLES,
        self::VIEW_PAYMENT_METHOD => self::ALL_ROLES,
        self::VIEW_PROCEDURE_CATALOG => self::ALL_ROLES,
        self::VIEW_SPECIALTY => self::ALL_ROLES,

        self::CREATE_PATIENT => ['administrador', 'recepcionista'],
        self::UPDATE_PATIENT => self::APPOINTMENT_ROLES,
        self::DELETE_PATIENT => ['administrador'],

        self::MANAGE_APPOINTMENT => self::APPOINTMENT_ROLES,
        self::UPDATE_APPOINTMENT_STATUS => self::APPOINTMENT_ROLES,
        self::VIEW_CONSULTATION => self::APPOINTMENT_ROLES,
        self::CHECK_IN_APPOINTMENT => self::APPOINTMENT_ROLES,
        self::ANNOUNCE_PATIENT => ['administrador', 'recepcionista'],
        self::START_CONSULTATION => self::MEDICAL_ROLES,
        self::VIEW_MY_DAY => self::MEDICAL_ROLES,
        self::VIEW_CONTINUITY => self::APPOINTMENT_ROLES,
        self::MANAGE_CONTINUITY => ['administrador', 'recepcionista'],
        // #280: closing a consultation writes clinical records, so reception
        // must not reach it. This is the only deliberate behavior change
        // against the pre-catalog role lists.
        self::COMPLETE_APPOINTMENT => self::MEDICAL_ROLES,
        self::CREATE_ENCOUNTER => self::MEDICAL_ROLES,
        self::VOID_ENCOUNTER => self::MEDICAL_ROLES,
        self::CLOSE_ENCOUNTER => self::MEDICAL_ROLES,
        self::VIEW_ENCOUNTER => self::MEDICAL_ROLES,
        self::VIEW_DIAGNOSIS_CATALOG => self::MEDICAL_ROLES,
        self::VIEW_PRODUCT => self::APPOINTMENT_ROLES,
        self::VIEW_INVENTORY => ['administrador', 'odontologo', 'implantologo', 'tecnico_dental', 'asistente', 'finanzas'],
        self::MANAGE_INVENTORY => ['administrador', 'finanzas', 'asistente'],
        self::MANAGE_REMINDER => self::APPOINTMENT_ROLES,
        self::SEND_REMINDER => self::APPOINTMENT_ROLES,
        self::MANAGE_REMINDER_TEMPLATE => ['administrador'],

        self::MANAGE_MEDICAL_RECORD => self::MEDICAL_ROLES,
        self::MANAGE_EVOLUTION => self::MEDICAL_ROLES,
        self::MANAGE_ATTACHMENT => self::MEDICAL_ROLES,
        self::VIEW_MEDICAL_RECORD_STATS => self::MEDICAL_ROLES,

        self::MANAGE_TREATMENT_PLAN => self::TREATMENT_ROLES,
        self::VIEW_QUOTATION => self::QUOTATION_READERS,
        self::MANAGE_QUOTATION => self::QUOTATION_WRITERS,
        self::MANAGE_SPECIALTY_RECORD => self::TREATMENT_ROLES,
        self::VIEW_DENTAL_PIECE => self::TREATMENT_ROLES,

        self::MANAGE_PROCEDURE_CATALOG => ['administrador'],
        self::IMPORT_PROCEDURE_CATALOG => self::FINANCE_ROLES,
        self::MANAGE_PROCEDURE_FAVORITE => self::CLINICAL,
        self::VIEW_PROCEDURE_STATS => self::FINANCE_ROLES,

        self::VIEW_BILLING => self::CASH_ROLES,
        self::CREATE_QUOTATION_FROM_APPOINTMENT => self::CASH_ROLES,
        self::VIEW_CASH_REGISTER => self::CASH_ROLES,
        self::OPEN_CASH_REGISTER => self::CASH_ROLES,
        self::CLOSE_CASH_REGISTER => self::CASH_ROLES,
        self::MANAGE_CASH_MOVEMENT => self::FINANCE_ROLES,
        self::MANAGE_TRANSACTION => self::CASH_ROLES,
        self::CREATE_TRANSACTION => self::CASH_ROLES,
        self::VOID_TRANSACTION => ['administrador'],
        self::VIEW_PENDING_PAYMENT => self::CASH_ROLES,
        self::PAY_PENDING_PAYMENT => self::CASH_ROLES,
        self::VIEW_CASH_REPORT => self::FINANCE_ROLES,
        self::EXPORT_CASH_REPORT => self::FINANCE_ROLES,
        self::MANAGE_PAYMENT_METHOD => ['administrador'],
        self::MANAGE_PAYMENT_MERCADOPAGO => self::FINANCE_ROLES,

        self::MANAGE_USER => ['administrador'],
        self::VIEW_AUDIT_LOG => ['administrador'],
        self::MANAGE_SPECIALTY => ['administrador'],
        self::MANAGE_DENTAL_CHAIR => ['administrador'],
        self::MANAGE_APPOINTMENT_TYPE => ['administrador'],
        self::MANAGE_BRANCH => ['administrador'],
        self::MANAGE_BRANCH_PRICE => ['administrador'],
        self::VIEW_PAYER => self::QUOTATION_READERS,
        self::MANAGE_PAYER => ['administrador'],
        self::MANAGE_PAYER_PRICE => ['administrador'],
        self::MANAGE_ACCOUNTING_LOCK => ['administrador'],
        self::OVERRIDE_ACCOUNTING_LOCK => ['administrador'],
        self::VIEW_REPORT => self::FINANCE_ROLES,
        self::EXPORT_REPORT => self::FINANCE_ROLES,

        self::VIEW_AI_ANALYSIS => self::AI_ROLES,
        self::CREATE_AI_ANALYSIS => self::AI_ROLES,
        self::REVIEW_AI_ANALYSIS => self::AI_ROLES,
        self::VOID_AI_ANALYSIS => self::AI_ROLES,

        self::MANAGE_CONFIG => ['administrador'],
        self::MANAGE_CASH_REGISTER => self::FINANCE_ROLES,
        self::APPLY_TRANSACTION_DISCOUNT => self::FINANCE_ROLES,
        self::APPLY_LARGE_TRANSACTION_DISCOUNT => ['administrador'],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::GRANTS);
    }

    /**
     * @return list<string>
     */
    public static function forRole(?string $role): array
    {
        if ($role === null) {
            return [];
        }

        $permissions = [];

        foreach (self::GRANTS as $permission => $roles) {
            if (in_array($role, $roles, true)) {
                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public static function rolesFor(string $permission): array
    {
        return self::GRANTS[$permission] ?? [];
    }

    public static function roleHas(?string $role, string $permission): bool
    {
        return $role !== null && in_array($role, self::rolesFor($permission), true);
    }

    public static function exists(string $permission): bool
    {
        return array_key_exists($permission, self::GRANTS);
    }
}
