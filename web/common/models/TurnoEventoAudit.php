<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $id_turno
 * @property string $tipo_evento
 * @property int|null $id_user
 * @property string|null $meta_json
 * @property string $created_at
 */
class TurnoEventoAudit extends ActiveRecord
{
    const TIPO_CONFIRMED = 'CONFIRMED';
    const TIPO_CANCEL_PAT = 'CANCEL_PAT';
    const TIPO_CANCEL_MED = 'CANCEL_MED';
    const TIPO_NO_SHOW = 'NO_SHOW';
    const TIPO_MODALITY_CHANGE = 'MODALITY_CHANGE';
    const TIPO_SOBRETURNO = 'SOBRETURNO';
    const TIPO_BULK_DAY_CANCEL = 'BULK_DAY_CANCEL';
    const TIPO_CREATE = 'CREATE';

    public static function tableName()
    {
        return '{{%turno_evento_audit}}';
    }

    public function rules()
    {
        return [
            [['id_turno', 'tipo_evento'], 'required'],
            [['id_turno', 'id_user'], 'integer'],
            [['meta_json'], 'string'],
            [['tipo_evento'], 'string', 'max' => 40],
        ];
    }

    public static function registrar($idTurno, $tipo, $idUser = null, array $meta = [])
    {
        $r = new static();
        $r->id_turno = (int) $idTurno;
        $r->tipo_evento = $tipo;
        $r->id_user = $idUser;
        $r->meta_json = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $r->save(false);
    }
}
