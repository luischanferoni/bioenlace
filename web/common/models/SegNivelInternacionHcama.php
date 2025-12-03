<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "seg_nivel_internacion_hcama".
 *
 * @property int $id
 * @property int $id_internacion
 * @property int $id_cama
 * @property string $fecha_ingreso
 * @property string|null $motivo
 *
 * @property InfraestructuraCama $cama
 * @property SegNivelInternacion $internacion
 */
class SegNivelInternacionHcama extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'seg_nivel_internacion_hcama';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_internacion', 'id_cama', 'motivo'], 'required'],
            [['id_internacion', 'id_cama'], 'integer'],
            [['fecha_ingreso'], 'safe'],
            [['motivo'], 'string', 'max' => 128],
            [['id_cama'], 'exist', 'skipOnError' => true, 'targetClass' => InfraestructuraCama::className(), 'targetAttribute' => ['id_cama' => 'id']],
            [['id_internacion'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacion::className(), 'targetAttribute' => ['id_internacion' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_internacion' => 'Id Internacion',
            'id_cama' => 'Nueva Cama',
            'fecha_ingreso' => 'Fecha Ingreso',
            'motivo' => 'Motivo',
        ];
    }

    /**
     * Gets query for [[Cama]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCama()
    {
        return $this->hasOne(InfraestructuraCama::className(), ['id' => 'id_cama']);
    }

    /**
     * Gets query for [[Internacion]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInternacion()
    {
        return $this->hasOne(SegNivelInternacion::className(), ['id' => 'id_internacion']);
    }
    
    public static function findByInternacionId($id) {
        return self::findByCondition([
            'id_internacion' => $id,
        ]);
    }
    
    protected static function getQueryForSelect() {
        $query = new \yii\db\Query();
        $query->select([
                    'code' => 'c.id',
                    'label' => "CONCAT('Cama N°', c.nro_cama, ' / Sala ', s.descripcion, ' / ', p.descripcion )"
                ])
                ->from(['c' => InfraestructuraCama::tableName()])
                ->leftJoin(
                        ['s' => InfraestructuraSala::tableName()],
                        's.id = c.id_sala')
                ->leftJoin(
                        ['p' => InfraestructuraPiso::tableName()],
                        'p.id = s.id_piso')
                ;
        return $query;
    }
    
    public static function getCamasDisponiblesForSelect($id_efector=null) {
        $query = self::getQueryForSelect()
                ->where(['c.estado' => 'desocupada'])
                ;
        if(null !== $id_efector) {
            $query->andWhere(['p.id_efector' => $id_efector]);
        }
        return $query->all();
    }
    
    public static function getCamaActualLabel($id_cama) {
        $query = self::getQueryForSelect()
                ->where(['c.id' => $id_cama])
                ;
        return $query->one();
    }
}
