<?php

namespace common\models\sumar;

use Yii;
use common\models\BeneficiarioSumar;
use common\models\Clinical\Encounter;
use common\models\ProfesionalEfectorServicio;
use common\traits\LegacyIdConsultaAsEncounterColumnTrait;

/**
 * Registro de envío / autofacturación SUMAR vinculado a un encounter.
 *
 * @property-read Encounter|null $encounter
 * @property-read BeneficiarioSumar|null $beneficiario
 * @property-read \common\models\Persona|null $profesionalPersona Persona del profesional vía PES.
 * @property-read ProfesionalEfectorServicio|null $profesionalEfectorServicio
 */
class Autofacturacion extends \yii\db\ActiveRecord
{
    use LegacyIdConsultaAsEncounterColumnTrait;
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
        if ($insert || $this->isAttributeChanged(static::legacyConsultaFkAttribute(), false)) {
            $this->syncProfesionalEfectorServicioFromContext();
        }
        return true;
    }

    public function syncProfesionalEfectorServicioFromContext(): void
    {
        $encounterId = $this->getEncounter_id();
        if (!$encounterId) {
            $this->id_profesional_efector_servicio = null;
            return;
        }
        $encounter = Encounter::findOne($encounterId);
        if ($encounter !== null && (int) $encounter->id_profesional_efector_servicio > 0) {
            $this->id_profesional_efector_servicio = (int) $encounter->id_profesional_efector_servicio;
            return;
        }
        $this->id_profesional_efector_servicio = null;
    }

    public function rules()
    {
        $fk = static::legacyConsultaFkAttribute();

        return [
            [[$fk, 'beneficiarios', 'codigos'], 'required'],
            [[$fk, 'beneficiario_enviado', 'id_profesional_efector_servicio'], 'integer'],
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
            static::legacyConsultaFkAttribute() => 'Encounter',
            'id_efector' => 'Efector',
            'beneficiarios' => 'Beneficiario Sumar',
            'codigos' => 'Codigos Mapeados',
            'codigo_enviado' => 'Codigo Enviado',
            'fecha_envio' => 'Fecha de Envío',
        ];
    }

    /** @deprecated use {@see getEncounter()} */
    public function getConsulta()
    {
        return $this->getEncounter();
    }

    /**
     * Beneficiario SUMAR cuando aplica vínculo por `id_beneficiario`.
     */
    public function getBeneficiario()
    {
        return $this->hasOne(BeneficiarioSumar::className(), ['id_beneficiarios' => 'id_beneficiario']);
    }

    public function getProfesionalPersona()
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