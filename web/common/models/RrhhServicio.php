<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "rrhh_servicio".
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
     * Gets query for [[Agenda_rrhh]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAgenda()
    {
        return $this->hasOne(Agenda_rrhh::className(), ['id_rrhh_servicio_asignado' => 'id']);
    }

    public static function rrhhPorEfectorConAgenda($id_efector)
    {
        $query = RrhhServicio::find()
            ->innerJoin('rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
            ->innerJoin('servicios', 'rrhh_servicio.id_servicio = servicios.id_servicio')
            ->andWhere(['servicios.acepta_turnos' => 'SI'])
            ->andWhere(['rrhh_efector.id_efector' => $id_efector])
            ->orderBy('rrhh_servicio.id_servicio');

        return $query->all();
    }   
    
    
    public static function obtenerIdRrhhServicio($id_rr_hh, $id_servicio)
    {
        $rrhhServicio = self::find()
        ->where(['id_rr_hh'=>$id_rr_hh])
        ->andWhere(['id_servicio'=>$id_servicio])
        ->one();

        if($rrhhServicio){

        return $rrhhServicio->id;

    }
    
    return false;

    }

    /**
     * Query: RRHH que atienden un servicio en un efector (para búsqueda de slots).
     * Flujo MVC: usado por Controller/Component que orquesta; las queries viven en el modelo.
     *
     * @param int $idServicio
     * @param int $idEfector
     * @return RrhhServicio[]
     */
    public static function findPorServicioEfector($idServicio, $idEfector)
    {
        return static::find()
            ->from(['rs' => static::tableName()])
            ->leftJoin('rrhh_efector re', 're.id_rr_hh = rs.id_rr_hh')
            ->andWhere(['re.id_efector' => (int) $idEfector])
            ->andWhere(['rs.id_servicio' => (int) $idServicio])
            ->all();
    }


}
