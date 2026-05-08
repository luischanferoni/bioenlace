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

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert
            || $this->isAttributeChanged('id_consulta', false)
            || $this->isAttributeChanged('id_rr_hh', false)
        ) {
            $this->syncProfesionalEfectorServicioFromContext();
        }
        return true;
    }

    public function syncProfesionalEfectorServicioFromContext(): void
    {
        if (!$this->id_consulta) {
            $this->id_profesional_efector_servicio = null;
            return;
        }
        $c = \common\models\Consulta::findOne($this->id_consulta);
        if ($c && (int) $c->id_profesional_efector_servicio > 0) {
            $this->id_profesional_efector_servicio = (int) $c->id_profesional_efector_servicio;
            return;
        }
        if ($this->id_rr_hh && $c && $c->id_efector && $c->id_servicio) {
            $re = \common\models\RrhhEfector::find()
                ->where(['id_rr_hh' => $this->id_rr_hh, 'id_efector' => $c->id_efector])
                ->andWhere(['deleted_at' => null])
                ->one();
            if ($re) {
                $this->id_profesional_efector_servicio = \common\models\ProfesionalEfectorServicio::findIdByPersonaEfectorServicio(
                    (int) $re->id_persona,
                    (int) $c->id_efector,
                    (int) $c->id_servicio
                );
                return;
            }
        }
        $this->id_profesional_efector_servicio = null;
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_consulta', 'beneficiarios', 'codigos', 'id_rr_hh'], 'required'],
            [['id_consulta', 'id_rr_hh', 'beneficiario_enviado', 'id_profesional_efector_servicio'], 'integer'],
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

    public function getProfesionalEfectorServicio()
    {
        return $this->hasOne(\common\models\ProfesionalEfectorServicio::className(), ['id' => 'id_profesional_efector_servicio']);
    }

    public static function getUltimaPorEfector()
    {
        return Autofacturacion::find()
                ->where(['=', 'id_efector', Yii::$app->user->getIdEfector()])
                ->orderBy(['id_sumar_autofacturacion' => SORT_DESC])
                ->one();
    }
}