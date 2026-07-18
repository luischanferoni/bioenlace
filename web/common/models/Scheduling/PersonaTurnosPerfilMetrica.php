<?php

namespace common\models\Scheduling;

use yii\db\ActiveRecord;

/**
 * Métrica explicable de un snapshot de {@see PersonaTurnosPerfil}.
 *
 * @property int $id
 * @property int $id_perfil
 * @property string $scope_type
 * @property string $scope_id
 * @property int $window_days
 * @property string $metric_code
 * @property int $numerator
 * @property int|null $denominator
 * @property string|null $value
 * @property int $sample_size
 * @property string $confidence_status
 */
class PersonaTurnosPerfilMetrica extends ActiveRecord
{
    public const SCOPE_GLOBAL = 'GLOBAL';
    public const SCOPE_EFECTOR = 'EFECTOR';
    public const SCOPE_SERVICIO = 'SERVICIO';
    public const SCOPE_MODALIDAD = 'MODALIDAD';

    public const CONFIDENCE_OK = 'OK';
    public const CONFIDENCE_INSUFFICIENT_DATA = 'INSUFFICIENT_DATA';
    public const CONFIDENCE_NOT_APPLICABLE = 'NOT_APPLICABLE';

    public static function tableName()
    {
        return '{{%persona_turnos_perfil_metrica}}';
    }

    /** @return list<string> */
    public static function scopeTypeValues(): array
    {
        return [
            self::SCOPE_GLOBAL,
            self::SCOPE_EFECTOR,
            self::SCOPE_SERVICIO,
            self::SCOPE_MODALIDAD,
        ];
    }

    /** @return list<string> */
    public static function confidenceStatusValues(): array
    {
        return [
            self::CONFIDENCE_OK,
            self::CONFIDENCE_INSUFFICIENT_DATA,
            self::CONFIDENCE_NOT_APPLICABLE,
        ];
    }

    public function rules()
    {
        return [
            [['id_perfil', 'scope_type', 'window_days', 'metric_code', 'numerator', 'sample_size', 'confidence_status'], 'required'],
            [['id_perfil', 'window_days', 'numerator', 'denominator', 'sample_size'], 'integer'],
            [['value'], 'number'],
            [['scope_id'], 'string', 'max' => 64],
            [['scope_id'], 'default', 'value' => ''],
            [['metric_code'], 'string', 'max' => 64],
            [['scope_type'], 'in', 'range' => self::scopeTypeValues()],
            [['confidence_status'], 'in', 'range' => self::confidenceStatusValues()],
        ];
    }

    public function getPerfil()
    {
        return $this->hasOne(PersonaTurnosPerfil::class, ['id' => 'id_perfil']);
    }
}
