<?php

namespace common\components\Core\Service\Push;

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

    /** Expediente legal PDF listo para descarga (staff solicitante). */
    public const LEGAL_RECORD_EXPORT_READY = 'LEGAL_RECORD_EXPORT_READY';

    public const GENERICO = 'GENERICO';
}
