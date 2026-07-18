<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Auditoría / stream canónico de eventos de turno.
 *
 * @property int $id
 * @property int $id_turno
 * @property string $tipo_evento
 * @property int|null $id_user
 * @property string|null $meta_json JSON; en cancelaciones suele incluir `razon_cancelacion`, `razon_cancelacion_label`, `canal`
 * @property string $created_at
 * @property int|null $id_persona
 * @property string|null $event_code
 * @property string|null $occurred_at
 * @property string|null $actor_type
 * @property string|null $channel
 * @property string|null $origin
 * @property string|null $motivo_normalizado
 * @property string|null $idempotency_key
 * @property string|null $attribution_quality
 * @property int|null $corrected_event_id
 * @property int|null $id_turno_relacionado
 * @property string|null $related_turno_role
 * @property string|null $appointment_at
 * @property int|null $id_efector
 * @property int|null $id_servicio
 * @property int|null $id_profesional_efector_servicio
 * @property string|null $modalidad
 */
class TurnoEventoAudit extends ActiveRecord
{
    /** Tipos legacy (compat UI / callers antiguos). */
    const TIPO_CONFIRMED = 'CONFIRMED';
    const TIPO_CANCEL_PAC = 'CANCEL_PAC';
    const TIPO_CANCEL_MED = 'CANCEL_MED';
    const TIPO_NO_SHOW = 'NO_SHOW';
    const TIPO_MODALITY_CHANGE = 'MODALITY_CHANGE';
    const TIPO_SOBRETURNO = 'SOBRETURNO';
    const TIPO_BULK_DAY_CANCEL = 'BULK_DAY_CANCEL';
    const TIPO_CREATE = 'CREATE';

    /** Códigos canónicos (contrato V1). */
    public const EVENT_APPOINTMENT_CREATED = 'APPOINTMENT_CREATED';
    public const EVENT_APPOINTMENT_RESCHEDULED = 'APPOINTMENT_RESCHEDULED';
    public const EVENT_APPOINTMENT_CANCELLED = 'APPOINTMENT_CANCELLED';
    public const EVENT_APPOINTMENT_ENTERED_RESOLUTION = 'APPOINTMENT_ENTERED_RESOLUTION';
    public const EVENT_CONFIRMATION_REQUESTED = 'CONFIRMATION_REQUESTED';
    public const EVENT_CONFIRMATION_DELIVERY_CONFIRMED = 'CONFIRMATION_DELIVERY_CONFIRMED';
    public const EVENT_CONFIRMATION_OPENED = 'CONFIRMATION_OPENED';
    public const EVENT_CONFIRMED = 'CONFIRMED';
    public const EVENT_ATTENTION_STARTED = 'ATTENTION_STARTED';
    public const EVENT_ATTENDED = 'ATTENDED';
    public const EVENT_NO_SHOW_RECORDED = 'NO_SHOW_RECORDED';
    public const EVENT_NO_SHOW_CORRECTED = 'NO_SHOW_CORRECTED';
    public const EVENT_APPOINTMENT_ADVANCE_OFFERED = 'APPOINTMENT_ADVANCE_OFFERED';
    public const EVENT_APPOINTMENT_ADVANCE_DELIVERED = 'APPOINTMENT_ADVANCE_DELIVERED';
    public const EVENT_APPOINTMENT_ADVANCE_OPENED = 'APPOINTMENT_ADVANCE_OPENED';
    public const EVENT_APPOINTMENT_ADVANCE_ACCEPTED = 'APPOINTMENT_ADVANCE_ACCEPTED';
    public const EVENT_APPOINTMENT_ADVANCE_UNAVAILABLE = 'APPOINTMENT_ADVANCE_UNAVAILABLE';
    public const EVENT_APPOINTMENT_ADVANCE_EXPIRED = 'APPOINTMENT_ADVANCE_EXPIRED';
    public const EVENT_SYSTEM_SLOT_RELEASED = 'SYSTEM_SLOT_RELEASED';
    public const EVENT_MODALITY_CHANGED = 'MODALITY_CHANGED';
    public const EVENT_OVERBOOK_CREATED = 'OVERBOOK_CREATED';

    public const ACTOR_PACIENTE = 'PACIENTE';
    public const ACTOR_REPRESENTANTE = 'REPRESENTANTE';
    public const ACTOR_STAFF = 'STAFF';
    public const ACTOR_EFECTOR = 'EFECTOR';
    public const ACTOR_SISTEMA = 'SISTEMA';
    public const ACTOR_EXTERNO = 'EXTERNO';

    public const QUALITY_NATIVE = 'NATIVE';

    public const RELATED_PREVIOUS = 'PREVIOUS';
    public const RELATED_NEW = 'NEW';

    /** @var array<string, string> Códigos almacenados en {@see $tipo_evento} → texto para UI e informes */
    private const ETIQUETAS_TIPO_EVENTO_ES = [
        self::TIPO_CONFIRMED => 'Confirmado',
        self::TIPO_CANCEL_PAC => 'Cancelación por paciente',
        self::TIPO_CANCEL_MED => 'Cancelación por profesional',
        self::TIPO_NO_SHOW => 'Inasistencia',
        self::TIPO_MODALITY_CHANGE => 'Cambio de modalidad',
        self::TIPO_SOBRETURNO => 'Sobreturno',
        self::TIPO_BULK_DAY_CANCEL => 'Cancelación masiva por día',
        self::TIPO_CREATE => 'Creación de turno',
        self::EVENT_APPOINTMENT_CREATED => 'Turno creado',
        self::EVENT_APPOINTMENT_RESCHEDULED => 'Turno reprogramado',
        self::EVENT_APPOINTMENT_CANCELLED => 'Turno cancelado',
        self::EVENT_APPOINTMENT_ENTERED_RESOLUTION => 'Turno en resolución',
        self::EVENT_CONFIRMATION_REQUESTED => 'Confirmación solicitada',
        self::EVENT_CONFIRMATION_DELIVERY_CONFIRMED => 'Confirmación entregada',
        self::EVENT_CONFIRMATION_OPENED => 'Confirmación abierta',
        self::EVENT_CONFIRMED => 'Asistencia confirmada',
        self::EVENT_ATTENTION_STARTED => 'Atención iniciada',
        self::EVENT_ATTENDED => 'Atendido',
        self::EVENT_NO_SHOW_RECORDED => 'Inasistencia registrada',
        self::EVENT_NO_SHOW_CORRECTED => 'Inasistencia corregida',
        self::EVENT_APPOINTMENT_ADVANCE_OFFERED => 'Oferta de adelantamiento',
        self::EVENT_APPOINTMENT_ADVANCE_DELIVERED => 'Oferta de adelantamiento entregada',
        self::EVENT_APPOINTMENT_ADVANCE_OPENED => 'Oferta de adelantamiento abierta',
        self::EVENT_APPOINTMENT_ADVANCE_ACCEPTED => 'Adelantamiento aceptado',
        self::EVENT_APPOINTMENT_ADVANCE_UNAVAILABLE => 'Adelantamiento no disponible',
        self::EVENT_APPOINTMENT_ADVANCE_EXPIRED => 'Oferta de adelantamiento vencida',
        self::EVENT_SYSTEM_SLOT_RELEASED => 'Cupo liberado por sistema',
        self::EVENT_MODALITY_CHANGED => 'Cambio de modalidad',
        self::EVENT_OVERBOOK_CREATED => 'Sobreturno',
    ];

    public static function tableName()
    {
        return '{{%turno_evento_audit}}';
    }

    /** @return list<string> */
    public static function actorTypeValues(): array
    {
        return [
            self::ACTOR_PACIENTE,
            self::ACTOR_REPRESENTANTE,
            self::ACTOR_STAFF,
            self::ACTOR_EFECTOR,
            self::ACTOR_SISTEMA,
            self::ACTOR_EXTERNO,
        ];
    }

    /** @return list<string> */
    public static function attributionQualityValues(): array
    {
        return [
            self::QUALITY_NATIVE,
        ];
    }

    public function rules()
    {
        return [
            [['id_turno', 'tipo_evento'], 'required'],
            [[
                'id_turno',
                'id_user',
                'id_persona',
                'corrected_event_id',
                'id_turno_relacionado',
                'id_efector',
                'id_servicio',
                'id_profesional_efector_servicio',
            ], 'integer'],
            [['meta_json'], 'string'],
            [['tipo_evento', 'event_code'], 'string', 'max' => 64],
            [['channel'], 'string', 'max' => 32],
            [['origin', 'motivo_normalizado', 'idempotency_key'], 'string', 'max' => 160],
            [['related_turno_role'], 'string', 'max' => 16],
            [['occurred_at', 'created_at', 'appointment_at'], 'safe'],
            [['modalidad'], 'string', 'max' => 20],
            [['actor_type'], 'in', 'range' => self::actorTypeValues(), 'skipOnEmpty' => true],
            [['attribution_quality'], 'in', 'range' => self::attributionQualityValues(), 'skipOnEmpty' => true],
        ];
    }

    /**
     * Compatibilidad: delega al servicio canónico idempotente.
     *
     * @param int $idTurno
     * @param string $tipo
     * @param int|null $idUser
     * @param array<string, mixed> $meta
     * @return static|null
     */
    public static function registrar($idTurno, $tipo, $idUser = null, array $meta = [])
    {
        return (new \common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventService())
            ->recordFromLegacy((int) $idTurno, (string) $tipo, $idUser !== null ? (int) $idUser : null, $meta);
    }

    /**
     * Etiqueta en español del tipo de evento (el valor en BD sigue siendo el código en inglés).
     */
    public static function etiquetaTipoEvento(string $tipo): string
    {
        return self::ETIQUETAS_TIPO_EVENTO_ES[$tipo] ?? $tipo;
    }

    public function getEtiquetaTipoEvento(): string
    {
        $code = (string) ($this->event_code ?: $this->tipo_evento);

        return self::etiquetaTipoEvento($code);
    }
}
