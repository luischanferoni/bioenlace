<?php

namespace common\models\busquedas;

use common\models\Clinical\Encounter;
use common\models\Referencia;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\User;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Listado de referencias sobre {@see Encounter} (sin tabla `consultas`).
 */
class ReferenciasBusquedas extends Referencia
{
    public function rules()
    {
        $fk = static::legacyConsultaFkAttribute();

        return [
            [['id_referencia', $fk, 'id_efector_referenciado', 'id_motivo_derivacion', 'id_servicio', 'id_estado'], 'integer'],
            [['estudios_complementarios', 'resumen_hc', 'tratamiento_previo', 'tratamiento', 'fecha_turno', 'hora_turno', 'observacion'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $id_user = Yii::$app->user->id;
        $id_efector = Yii::$app->user->idEfector;
        $fk = static::legacyConsultaFkAttribute();
        $encTable = Encounter::tableName();

        $idPersonaSesion = (int) Yii::$app->user->getIdPersona();
        if ($idPersonaSesion <= 0) {
            $idPersonaSesion = (int) Persona::find()->select(['id_persona'])->where(['id_user' => $id_user])->scalar();
        }
        $pesIdsProfesional = [];
        if ($idPersonaSesion > 0) {
            $pesIdsProfesional = ProfesionalEfectorServicio::find()
                ->select(['id'])
                ->where(['id_persona' => $idPersonaSesion, 'deleted_at' => null])
                ->column();
        }

        $select = [
            'id_referencia' => 'referencia.id_referencia',
            'id_consulta' => 'enc.id',
            'encounter_id' => 'enc.id',
            'id_efector_referenciado' => 'referencia.id_efector_referenciado',
            'id_motivo_derivacion' => 'referencia.id_motivo_derivacion',
            'id_servicio' => 'referencia.id_servicio',
            'id_estado' => 'referencia.id_estado',
            'fecha_turno' => 'referencia.fecha_turno',
            'hora_turno' => 'referencia.hora_turno',
        ];

        $query = Referencia::find()
            ->select($select)
            ->from('referencia')
            ->innerJoin(['enc' => $encTable], "referencia.{$fk} = enc.id")
            ->leftJoin('turnos', 'enc.appointment_id = turnos.id_turnos')
            ->innerJoin('efectores', 'turnos.id_efector = efectores.id_efector')
            ->where(['turnos.id_efector' => $id_efector]);

        if (User::hasRole(['Medico'], true)) {
            if ($pesIdsProfesional !== []) {
                $query->andWhere(['turnos.id_profesional_efector_servicio' => $pesIdsProfesional]);
            } else {
                $query->andWhere('0=1');
            }
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'referencia.id_referencia' => $this->id_referencia,
            "referencia.{$fk}" => $this->getEncounter_id(),
            'referencia.id_efector_referenciado' => $this->id_efector_referenciado,
            'referencia.id_motivo_derivacion' => $this->id_motivo_derivacion,
            'referencia.id_servicio' => $this->id_servicio,
            'referencia.id_estado' => $this->id_estado,
            'referencia.fecha_turno' => $this->fecha_turno,
            'referencia.hora_turno' => $this->hora_turno,
        ]);

        $query->andFilterWhere(['like', 'referencia.estudios_complementarios', $this->estudios_complementarios])
            ->andFilterWhere(['like', 'referencia.resumen_hc', $this->resumen_hc])
            ->andFilterWhere(['like', 'referencia.tratamiento_previo', $this->tratamiento_previo])
            ->andFilterWhere(['like', 'referencia.tratamiento', $this->tratamiento])
            ->andFilterWhere(['like', 'referencia.observacion', $this->observacion]);

        return $dataProvider;
    }
}
