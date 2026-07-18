<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Alerta in-app para una persona (bandeja; el push FCM es canal aparte).
 *
 * @property int $id
 * @property int $id_persona
 * @property string $tipo
 * @property string $titulo
 * @property string $cuerpo
 * @property string|null $data_json
 * @property string|null $leida_at
 * @property string $created_at
 * @property string|null $public_ref
 * @property string|null $idempotency_key
 * @property string|null $context_handler_id
 * @property string|null $context_json
 */
class PersonaNotificacion extends ActiveRecord
{
    public const TIPO_TURNO_REQUIERE_REUBICACION = 'TURNO_REQUIERE_REUBICACION';
    public const TIPO_TURNO_AUTO_REUBICADO_RESOLUCION = 'TURNO_AUTO_REUBICADO_RESOLUCION';
    public const TIPO_CONSULTA_ASYNC_SLA_ESCALATE_STAFF = 'CONSULTA_ASYNC_SLA_ESCALATE_STAFF';
    public const TIPO_TURNO_CANCELADO_EFECTOR = 'TURNO_CANCELADO_EFECTOR';
    public const TIPO_TURNO_RECORDATORIO = 'TURNO_RECORDATORIO';
    public const TIPO_TURNO_CONFIRMAR = 'TURNO_CONFIRMAR';

    public static function tableName()
    {
        return '{{%persona_notificacion}}';
    }

    public function rules()
    {
        return [
            [['id_persona', 'tipo', 'titulo', 'cuerpo'], 'required'],
            [['id_persona'], 'integer'],
            [['cuerpo', 'data_json', 'leida_at', 'context_json'], 'safe'],
            [['tipo'], 'string', 'max' => 64],
            [['titulo'], 'string', 'max' => 255],
            [['public_ref'], 'string', 'max' => 64],
            [['idempotency_key'], 'string', 'max' => 191],
            [['context_handler_id'], 'string', 'max' => 128],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options keys: idempotency_key, context_handler_id, context
     */
    public static function crear(
        int $idPersona,
        string $tipo,
        string $titulo,
        string $cuerpo,
        array $data = [],
        array $options = []
    ): self {
        $idempotencyKey = isset($options['idempotency_key']) ? trim((string) $options['idempotency_key']) : '';
        if ($idempotencyKey !== '') {
            $existing = static::findOne(['idempotency_key' => $idempotencyKey]);
            if ($existing !== null) {
                return $existing;
            }
        }

        $row = new static();
        $row->id_persona = $idPersona;
        $row->tipo = $tipo;
        $row->titulo = $titulo;
        $row->cuerpo = $cuerpo;
        $row->public_ref = bin2hex(random_bytes(16));
        if ($idempotencyKey !== '') {
            $row->idempotency_key = $idempotencyKey;
        }
        $handler = isset($options['context_handler_id']) ? trim((string) $options['context_handler_id']) : '';
        if ($handler !== '') {
            $row->context_handler_id = $handler;
        }
        $context = isset($options['context']) && is_array($options['context']) ? $options['context'] : [];
        if ($context !== []) {
            $row->context_json = json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $payload = $data;
        $payload['notification_ref'] = $row->public_ref;
        if ($handler !== '') {
            $payload['context_handler_id'] = $handler;
        }
        $row->data_json = $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

        try {
            $row->save(false);
        } catch (\yii\db\IntegrityException $e) {
            if ($idempotencyKey !== '') {
                $existing = static::findOne(['idempotency_key' => $idempotencyKey]);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $e;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeData(): array
    {
        if ($this->data_json === null || trim((string) $this->data_json) === '') {
            return [];
        }
        $decoded = json_decode((string) $this->data_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        // Reparación de doble encoding histórico.
        if (count($decoded) === 1 && isset($decoded[0]) && is_string($decoded[0])) {
            $inner = json_decode($decoded[0], true);
            if (is_array($inner)) {
                return $inner;
            }
        }
        if (isset($decoded['json']) && is_string($decoded['json'])) {
            $inner = json_decode($decoded['json'], true);
            if (is_array($inner)) {
                return $inner;
            }
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeContext(): array
    {
        if ($this->context_json === null || trim((string) $this->context_json) === '') {
            return [];
        }
        $decoded = json_decode((string) $this->context_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $data = $this->decodeData();
        if ($this->public_ref !== null && trim((string) $this->public_ref) !== '') {
            $data['notification_ref'] = (string) $this->public_ref;
        }

        return [
            'id' => (int) $this->id,
            'notification_ref' => $this->public_ref,
            'tipo' => (string) $this->tipo,
            'titulo' => (string) $this->titulo,
            'cuerpo' => (string) $this->cuerpo,
            'data' => $data,
            'leida' => $this->leida_at !== null && trim((string) $this->leida_at) !== '',
            'leida_at' => $this->leida_at,
            'created_at' => $this->created_at,
        ];
    }
}
