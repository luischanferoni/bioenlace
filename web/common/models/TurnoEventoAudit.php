<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $id_turno
 * @property string $tipo_evento
 * @property int|null $id_user
 * @property string|null $meta_json JSON; en cancelaciones suele incluir `razon_cancelacion`, `razon_cancelacion_label`, `canal`
 * @property string $created_at
 */
class TurnoEventoAudit extends ActiveRecord
{
    const TIPO_CONFIRMED = 'CONFIRMED';
    const TIPO_CANCEL_PAC = 'CANCEL_PAC';
    const TIPO_CANCEL_MED = 'CANCEL_MED';
    const TIPO_NO_SHOW = 'NO_SHOW';
    const TIPO_MODALITY_CHANGE = 'MODALITY_CHANGE';
    const TIPO_SOBRETURNO = 'SOBRETURNO';
    const TIPO_BULK_DAY_CANCEL = 'BULK_DAY_CANCEL';
    const TIPO_CREATE = 'CREATE';

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
    ];

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

    /**
     * Etiqueta en español del tipo de evento (el valor en BD sigue siendo el código en inglés).
     */
    public static function etiquetaTipoEvento(string $tipo): string
    {
        return self::ETIQUETAS_TIPO_EVENTO_ES[$tipo] ?? $tipo;
    }

    public function getEtiquetaTipoEvento(): string
    {
        return self::etiquetaTipoEvento((string) $this->tipo_evento);
    }
}
