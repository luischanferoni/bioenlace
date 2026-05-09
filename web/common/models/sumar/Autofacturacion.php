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
        $legacyRrhh = $this->hasAttribute('id_rr_hh');
        if ($insert
            || $this->isAttributeChanged('id_consulta', false)
            || ($legacyRrhh && $this->isAttributeChanged('id_rr_hh', false))
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
        if ($this->hasAttribute('id_rr_hh') && $this->getAttribute('id_rr_hh') && $c && $c->id_efector && $c->id_servicio) {
            $idPersona = \common\models\ProfesionalEfectorServicio::resolveIdPersonaFromIdRrhh((int) $this->getAttribute('id_rr_hh'));
            if ($idPersona !== null && $idPersona > 0) {
                $this->id_profesional_efector_servicio = \common\models\ProfesionalEfectorServicio::findIdByPersonaEfectorServicio(
                    $idPersona,
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
            [['id_consulta', 'beneficiarios', 'codigos'], 'required'],
            [['id_consulta', 'beneficiario_enviado', 'id_profesional_efector_servicio'], 'integer'],
            [['id_rr_hh'], 'integer', 'skipOnEmpty' => true],
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
        return $this->hasOne(\common\models\Rrhh::className(), ['id_rr_hh' => 'id_rr_hh']);
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