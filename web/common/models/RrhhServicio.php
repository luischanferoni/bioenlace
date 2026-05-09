<?php

namespace common\models;

use Yii;

/**
 * ActiveRecord de la tabla histórica `rrhh_servicio` (asignación RRHH–servicio legacy).
 *
 * El modelo operativo de agenda/asignación es {@see ProfesionalEfectorServicio}. Esta tabla puede seguir
 * existiendo por datos históricos o integraciones; no usar para nuevas features sin migrar a PES.
 *
 * @property int $id_rr_hh
 * @property int $id_servicio
 * @property string $horario
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 *
 * @property RrhhEfector $rrhhEfector
 * @property Servicio $servicio
 * @property ProfesionalEfectorServicioAgenda|null $agenda agenda operativa (PES), resuelta por efector del RRHH
 *
 */
class RrhhServicio extends \yii\db\ActiveRecord
{
    use \common\traits\SoftDeleteDateTimeTrait;
    
    public $select2_codigo;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'rrhh_servicio';
    }
    
    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => isset(Yii::$app->user->id) ? Yii::$app->user->id : 1,
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_rr_hh', 'id_servicio'], 'required'],
            [['id_rr_hh', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [
                'id_servicio', 'unique', 
                'message' => 'El usuario ya se encuentra asignado a uno de los servicios elegidos', 
                'targetAttribute' => ['id_rr_hh', 'id_servicio']
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_rr_hh' => 'Id RRHH',
            'id_servicio' => 'Servicio',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    /**
     * Gets query for [[RrhhEfector]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRrhhEfector()
    {
        return $this->hasOne(RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    /**
     * Gets query for [[Servicio]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServicio()
    {
        return $this->hasOne(Servicio::className(), ['id_servicio' => 'id_servicio']);
    }    

    /**
     * Agenda operativa (`profesional_efector_servicio_agenda`) para esta asignación, según el efector del RRHH.
     */
    public function getAgenda(): ?ProfesionalEfectorServicioAgenda
    {
        $re = $this->rrhhEfector;
        if ($re === null) {
            return null;
        }
        $idPes = ProfesionalEfectorServicio::resolveProfesionalEfectorServicioIdFromRrhhServicioId((int) $this->id, (int) $re->id_efector);
        if ($idPes === null) {
            return null;
        }

        return ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);
    }

    /**
     * @return ProfesionalEfectorServicio[]
     */
    public static function rrhhPorEfectorConAgenda($id_efector)
    {
        return ProfesionalEfectorServicio::find()
            ->alias('pes')
            ->innerJoin('servicios', 'servicios.id_servicio = pes.id_servicio')
            ->andWhere(['servicios.acepta_turnos' => 'SI'])
            ->andWhere(['pes.id_efector' => (int) $id_efector])
            ->andWhere(['pes.deleted_at' => null])
            ->orderBy('pes.id_servicio')
            ->all();
    }

    /**
     * Id de fila PES para el vínculo RRHH (id_rr_hh) + servicio, o false.
     *
     * @param int $id_rr_hh
     * @param int $id_servicio
     * @return int|false
     */
    public static function obtenerIdRrhhServicio($id_rr_hh, $id_servicio)
    {
        $re = RrhhEfector::find()
            ->where(['id_rr_hh' => $id_rr_hh, 'deleted_at' => null])
            ->one();
        if ($re === null) {
            return false;
        }
        $pes = ProfesionalEfectorServicio::find()
            ->where([
                'id_persona' => (int) $re->id_persona,
                'id_efector' => (int) $re->id_efector,
                'id_servicio' => (int) $id_servicio,
                'deleted_at' => null,
            ])
            ->one();

        return $pes !== null ? (int) $pes->id : false;
    }

    /**
     * PES que atienden un servicio en un efector (para búsqueda de slots).
     *
     * @param int $idServicio
     * @param int $idEfector
     * @return ProfesionalEfectorServicio[]
     */
    public static function findPorServicioEfector($idServicio, $idEfector)
    {
        return ProfesionalEfectorServicio::find()
            ->where([
                'id_efector' => (int) $idEfector,
                'id_servicio' => (int) $idServicio,
                'deleted_at' => null,
            ])
            ->all();
    }


}
