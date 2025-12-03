<?php

namespace common\models\sumar;

use Yii;

class Autofacturacion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sumar_autofacturacion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'beneficiarios', 'codigos', 'id_rr_hh'], 'required'],
            [['id_consulta', 'id_rr_hh', 'beneficiario_enviado'], 'integer'],
            [['codigos', 'codigo_enviado', 'beneficiarios'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_sumar_autofacturacion' => 'ID',
            'id_consulta' => 'Consulta',
            'id_efector' => 'Efector',
            'beneficiarios' => 'Baneficiario Sumar',
            'codigos' => 'Codigos Mapeados',
            'codigo_enviado' => 'Codigo Enviado',
            'fecha_envio' => 'Fecha de Envío',
            'id_rr_hh' => 'RRHH',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getConsulta()
    {
        return $this->hasMany(\common\models\Consulta::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBeneficiario()
    {
        return $this->hasOne(\common\models\BeneficiarioSumar::className(), ['id_beneficiarios' => 'id_beneficiario']);
    }

    public function getRrhhEfector()
    {
        return $this->hasOne(\common\models\RrhhEfector::className(), ['id_rr_hh' => 'id_rr_hh']);
    }

    public static function getUltimaPorEfector()
    {
        return Autofacturacion::find()
                ->where(['=', 'id_efector', Yii::$app->user->getIdEfector()])
                ->orderBy(['id_sumar_autofacturacion' => SORT_DESC])
                ->one();
    }
}