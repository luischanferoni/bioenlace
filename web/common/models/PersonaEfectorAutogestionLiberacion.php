<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Liberación de autogestión (tras presencial o llamada verificada).
 *
 * @property int $id
 * @property int $id_persona
 * @property int $id_efector
 * @property string $liberada_en
 * @property int|null $id_user
 * @property string|null $motivo
 */
class PersonaEfectorAutogestionLiberacion extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%persona_efector_autogestion_liberacion}}';
    }

    public function rules()
    {
        return [
            [['id_persona', 'id_efector'], 'required'],
            [['id_persona', 'id_efector', 'id_user'], 'integer'],
            [['liberada_en'], 'safe'],
            [['motivo'], 'string', 'max' => 255],
        ];
    }

    /**
     * Indica si hay liberación vigente según días de configuración del efector.
     */
    public static function tieneLiberacionVigente($idPersona, $idEfector, $vigenciaDias)
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . (int) $vigenciaDias . ' days'));
        return static::find()
            ->where([
                'id_persona' => (int) $idPersona,
                'id_efector' => (int) $idEfector,
            ])
            ->andWhere(['>=', 'liberada_en', $since])
            ->exists();
    }

    public static function registrar($idPersona, $idEfector, $idUser = null, $motivo = null)
    {
        $r = new static();
        $r->id_persona = (int) $idPersona;
        $r->id_efector = (int) $idEfector;
        $r->id_user = $idUser;
        $r->motivo = $motivo;
        $r->save(false);
        return $r;
    }
}
