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
 */
class PersonaNotificacion extends ActiveRecord
{
    public const TIPO_TURNO_REQUIERE_REUBICACION = 'TURNO_REQUIERE_REUBICACION';
    public const TIPO_TURNO_CANCELADO_EFECTOR = 'TURNO_CANCELADO_EFECTOR';
    public const TIPO_TURNO_RECORDATORIO = 'TURNO_RECORDATORIO';

    public static function tableName()
    {
        return '{{%persona_notificacion}}';
    }

    public function rules()
    {
        return [
            [['id_persona', 'tipo', 'titulo', 'cuerpo'], 'required'],
            [['id_persona'], 'integer'],
            [['cuerpo', 'data_json', 'leida_at'], 'safe'],
            [['tipo'], 'string', 'max' => 64],
            [['titulo'], 'string', 'max' => 255],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function crear(int $idPersona, string $tipo, string $titulo, string $cuerpo, array $data = []): self
    {
        $row = new static();
        $row->id_persona = $idPersona;
        $row->tipo = $tipo;
        $row->titulo = $titulo;
        $row->cuerpo = $cuerpo;
        $row->data_json = $data !== [] ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
        $row->save(false);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $data = [];
        if ($this->data_json !== null && trim((string) $this->data_json) !== '') {
            $decoded = json_decode((string) $this->data_json, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return [
            'id' => (int) $this->id,
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
