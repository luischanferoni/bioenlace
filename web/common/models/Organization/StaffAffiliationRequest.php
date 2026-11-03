<?php

namespace common\models\Organization;

use yii\db\ActiveRecord;

/**
 * Solicitud declarada al crear una cuenta personal gratuita: el profesional
 * indica en qué centro activo de Bioenlace trabaja.
 *
 * Se registra como intención; todavía no se aprueba ni asigna ningún rol.
 *
 * @property int $id
 * @property int $id_persona
 * @property int|null $id_user
 * @property int $id_efector
 * @property string $sector
 * @property string $nombre_efector
 * @property string|null $codigo_sisa
 * @property string|null $localidad
 * @property string|null $departamento
 * @property string|null $provincia
 * @property int|null $id_provincia
 * @property string $status
 */
class StaffAffiliationRequest extends ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;

    public const SECTOR_PUBLICO = 'PUBLICO';

    public const SECTOR_PRIVADO = 'PRIVADO';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public static function tableName(): string
    {
        return '{{%staff_affiliation_request}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => \yii\behaviors\TimestampBehavior::class,
                'value' => static function () {
                    return date('Y-m-d H:i:s');
                },
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['id_persona', 'id_efector', 'sector', 'nombre_efector'], 'required'],
            [['id_persona', 'id_user', 'id_efector', 'id_provincia'], 'integer'],
            [['sector'], 'in', 'range' => [self::SECTOR_PUBLICO, self::SECTOR_PRIVADO]],
            [['status'], 'in', 'range' => [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
            ]],
            [['nombre_efector'], 'string', 'max' => 255],
            [['codigo_sisa'], 'string', 'max' => 15],
            [['localidad'], 'string', 'max' => 100],
            [['departamento'], 'string', 'max' => 40],
            [['provincia'], 'string', 'max' => 20],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusValues(): array
    {
        return [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];
    }

    public function getEfector()
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector']);
    }
}
