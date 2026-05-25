<?php

namespace common\models\Emergency;

use common\models\Guardia;
use yii\db\ActiveRecord;

/**
 * Evento append-only del circuito de guardia.
 *
 * @property int $id
 * @property int $guardia_id
 * @property string $tipo
 * @property string $occurred_at
 * @property int|null $id_profesional_efector_servicio
 * @property string|null $payload_json
 * @property int|null $created_by
 */
class GuardiaCircuitoEvent extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%guardia_circuito_event}}';
    }

    public function rules(): array
    {
        return [
            [['guardia_id', 'tipo', 'occurred_at'], 'required'],
            [['guardia_id', 'id_profesional_efector_servicio', 'created_by'], 'integer'],
            [['tipo'], 'string', 'max' => 32],
            [['occurred_at', 'payload_json'], 'safe'],
        ];
    }

    public function getGuardia()
    {
        return $this->hasOne(Guardia::class, ['id' => 'guardia_id']);
    }

    public static function registrar(
        int $guardiaId,
        string $tipo,
        ?int $pesId = null,
        ?array $payload = null,
        ?int $createdBy = null
    ): self {
        $row = new self();
        $row->guardia_id = $guardiaId;
        $row->tipo = $tipo;
        $row->occurred_at = date('Y-m-d H:i:s');
        $row->id_profesional_efector_servicio = $pesId;
        $row->payload_json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        $row->created_by = $createdBy;
        if (!$row->save()) {
            throw new \RuntimeException('No se pudo registrar evento de circuito: ' . json_encode($row->errors));
        }

        return $row;
    }
}
