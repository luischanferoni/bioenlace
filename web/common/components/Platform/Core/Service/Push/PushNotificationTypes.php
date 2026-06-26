<?php

namespace common\components\Platform\Core\Service\Push;

/**
 * Valores de `type` en payload FCM / persona_notificacion (extensible por dominio).
 */
final class PushNotificationTypes
{
    // Turnos
    public const TURNO_REQUIERE_REUBICACION = 'TURNO_REQUIERE_REUBICACION';
    public const TURNO_CANCELADO_EFECTOR = 'TURNO_CANCELADO_EFECTOR';
    public const TURNO_REMINDER = 'TURNO_REMINDER';
    public const TURNO_TRANSPORT_HINT = 'TURNO_TRANSPORT_HINT';
    public const TURNO_CONFIRMAR = 'TURNO_CONFIRMAR';
    public const TURNO_RETRASO_SOBRETURNO = 'TURNO_RETRASO_SOBRETURNO';

    /** Resumen de atención ambulatoria publicado al paciente. */
    public const ENCOUNTER_SUMMARY_READY = 'ENCOUNTER_SUMMARY_READY';

    /** Touchpoint de seguimiento post-consulta (pack cohorte). */
    public const CARE_FOLLOWUP_TOUCHPOINT = 'CARE_FOLLOWUP_TOUCHPOINT';

    /** Alerta staff por rama de seguimiento cohorte (agente B01). */
    public const CARE_FOLLOWUP_STAFF_ALERT = 'CARE_FOLLOWUP_STAFF_ALERT';

    /** Laboratorio: resultado disponible para el paciente (agente B03). */
    public const LAB_RESULT_AVAILABLE = 'LAB_RESULT_AVAILABLE';

    /** Laboratorio: valor crítico — aviso urgente al paciente. */
    public const LAB_RESULT_CRITICAL_PATIENT = 'LAB_RESULT_CRITICAL_PATIENT';

    /** Laboratorio: valor crítico — aviso al profesional del encounter. */
    public const LAB_RESULT_CRITICAL_STAFF = 'LAB_RESULT_CRITICAL_STAFF';

    /** Expediente legal PDF listo para descarga (staff solicitante). */
    public const LEGAL_RECORD_EXPORT_READY = 'LEGAL_RECORD_EXPORT_READY';

    /** Guardia: paciente asignado al PES del médico. */
    public const EMERGENCY_ASSIGNED_TO_YOU = 'EMERGENCY_ASSIGNED_TO_YOU';

    /** Guardia: triage nivel 1–2 (Manchester). */
    public const EMERGENCY_PATIENT_CRITICAL = 'EMERGENCY_PATIENT_CRITICAL';

    /** Representante actuó por el paciente (N9, si el paciente activó la preferencia). */
    public const REPRESENTATIVE_ACTION = 'REPRESENTATIVE_ACTION';

    public const GENERICO = 'GENERICO';
}
