<?php

namespace common\models\sumar;

use Yii;
use common\models\BeneficiarioSumar;
use common\models\Consulta;
use common\models\ProfesionalEfectorServicio;

/**
 * Registro de envío / autofacturación SUMAR vinculado a una consulta.
 *
 * @property-read Consulta|null $consulta
 * @property-read BeneficiarioSumar|null $beneficiario
 * @property-read \common\models\Persona|null $rrhh Persona del profesional vía PES.
 * @property-read ProfesionalEfectorServicio|null $profesionalEfectorServicio
 */
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
            'beneficiarios' => 'Beneficiario Sumar',
            'codigos' => 'Codigos Mapeados',
            'codigo_enviado' => 'Codigo Enviado',
            'fecha_envio' => 'Fecha de Envío',
            'id_rr_hh' => 'RRHH',
        ];
    }

    /**
     * Consulta asociada (FK `id_consulta` en esta tabla).
     */
    public function getConsulta()
    {
        return $this->hasOne(Consulta::className(), ['id_consulta' => 'id_consulta']);
    }

    /**
     * Beneficiario SUMAR cuando aplica vínculo por `id_beneficiario`.
     */
    public function getBeneficiario()
    {
        return $this->hasOne(BeneficiarioSumar::className(), ['id_beneficiarios' => 'id_beneficiario']);
    }

    public function getRrhh()
    {
        return $this->hasOne(\common\models\Persona::className(), ['id_persona' => 'id_persona'])
            ->viaTable(ProfesionalEfectorServicio::tableName(), ['id' => 'id_profesional_efector_servicio']);
    }

    public function getProfesionalEfectorServicio()
    {
        return $this->hasOne(ProfesionalEfectorServicio::className(), ['id' => 'id_profesional_efector_servicio']);
    }

    public static function getUltimaPorEfector()
    {
        return Autofacturacion::find()
                ->where(['=', 'id_efector', Yii::$app->user->getIdEfector()])
                ->orderBy(['id_sumar_autofacturacion' => SORT_DESC])
                ->one();
    }
}