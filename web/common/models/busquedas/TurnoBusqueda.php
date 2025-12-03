<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Turno;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;

/**
 * TurnoBusqueda represents the model behind the search form about `common\models\Turno`.
 */
class TurnoBusqueda extends Turno
{
    public $busqueda_libre = false;
    public $dni;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_turnos', 'id_persona', 'id_rr_hh', 'id_consulta_referencia', 'id_servicio_asignado', 'id_rrhh_servicio_asignado'], 'integer'],
            [['fecha', 'hora', 'confirmado', 'referenciado', 'usuario_alta', 'fecha_alta', 'usuario_mod', 'fecha_mod', 'busqueda_libre', 'dni'], 'safe'],

        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {

        $query = Turno::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_turnos' => $this->id_turnos,
            'id_persona' => $this->id_persona,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'id_rrhh_servicio_asignado' => $this->id_rrhh_servicio_asignado,
            'id_consulta_referencia' => $this->id_consulta_referencia,
            'id_servicio_asignado' => $this->id_servicio_asignado,
            'fecha_alta' => $this->fecha_alta,
            'fecha_mod' => $this->fecha_mod,
        ]);

        $query->andFilterWhere(['like', 'confirmado', $this->confirmado])
            ->andFilterWhere(['like', 'referenciado', $this->referenciado])
            ->andFilterWhere(['like', 'usuario_alta', $this->usuario_alta])
            ->andFilterWhere(['like', 'usuario_mod', $this->usuario_mod]);
            

        if ($this->dni != '' && $this->busqueda_libre) {
            $query->joinWith(['persona' => function ($q) {
                $q->where(['documento' => $this->dni]);
            }]);

            $query->andFilterWhere(['<>', 'estado', Turno::ESTADO_ATENDIDO])
                ->orderBy('id_turnos DESC');

        }elseif($this->dni == '' && $this->busqueda_libre){
            $dataProvider = new ActiveDataProvider();
            $dataProvider->setTotalCount(-1);
            return $dataProvider;
        }

        return $dataProvider;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchAllTurnos($tfecha, $idRrhh)
    {
        $rrhh = RrhhEfector::findOne($idRrhh);
        $idsRrhhServicios = \Yii\helpers\ArrayHelper::getColumn($rrhh->rrhhServicio, 'id');
        $idsServicios = \Yii\helpers\ArrayHelper::getColumn($rrhh->rrhhServicio, 'id_servicio');

        $t = RrhhEfector::obtenerServicioActual();

        // Traigo los servicios que podrian requerir pasar por el servicio actual del rrhh
        $serviciosConPasePrevio = ServiciosEfector::find()
            ->andWhere(['servicios_efector.id_efector' => Yii::$app->user->getIdEfector()])
            ->andWhere(['in', 'servicios_efector.pase_previo', $idsServicios])
            ->all();
        $idServiciosConPasePrevio = \Yii\helpers\ArrayHelper::getColumn($serviciosConPasePrevio, 'id_servicio');

        $totalIdsServicios = array_unique(array_merge($idsServicios, $idServiciosConPasePrevio));


        $query = Turno::find();

        // add conditions that should always apply here


        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        //var_dump($this->id_rrhh_servicio_asignado);die;

        // grid filtering conditions
        $query->andFilterWhere([
            'id_turnos' => $this->id_turnos,
            'id_persona' => $this->id_persona,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'id_rrhh_servicio_asignado' => $this->id_rrhh_servicio_asignado,
            'id_consulta_referencia' => $this->id_consulta_referencia,
            'id_servicio_asignado' => $this->id_servicio_asignado,
            'fecha_alta' => $this->fecha_alta,
            'fecha_mod' => $this->fecha_mod,
        ]);


        if (isset($tfecha) && $tfecha != "") {
            $fecha = explode(' - ', $tfecha);
            $start = $fecha[0];
            $end = $fecha[1];
            $query->andFilterWhere(['between', 'fecha', $start, $end]);
        } else {
            $start = date('Y-m-d');
            $end = date('Y-m-d');
            $query->andFilterWhere(['between', 'fecha', $start, $end]);
        }

        /* $query->andFilterWhere(
            [
                'or',
                ['in', 'id_rrhh_servicio_asignado', $idsRrhhServicios],
                [
                    'and',
                    ['id_servicio_asignado' => $totalIdsServicios],
                    ['id_efector' => Yii::$app->user->getIdEfector()],
                ],                          
            ]) */
        #->andWhere(['estado' => 'PENDIENTE'])
        $query->andFilterWhere(['id_efector' => Yii::$app->user->getIdEfector()])
            ->orderBy('fecha', 'hora');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        return $dataProvider;
    }
}
