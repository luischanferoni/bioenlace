<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Turno;
use common\models\ProfesionalEfectorServicio;
use common\models\ServiciosEfector;

/**
 * TurnoBusqueda represents the model behind the search form about `common\models\Turno`.
 */
class TurnoBusqueda extends Turno
{
    public $busqueda_libre = false;
    public $dni;

    /** @var string|null Filtro profesional: id PES o `p` + id PES (p. ej. p42). */
    public $profesional_clave;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_turnos', 'id_persona', 'id_rr_hh', 'id_consulta_referencia', 'id_servicio_asignado', 'id_profesional_efector_servicio'], 'integer'],
            [['fecha', 'hora', 'confirmado', 'referenciado', 'usuario_alta', 'fecha_alta', 'usuario_mod', 'fecha_mod', 'busqueda_libre', 'dni', 'profesional_clave'], 'safe'],

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
            'id_consulta_referencia' => $this->id_consulta_referencia,
            'id_servicio_asignado' => $this->id_servicio_asignado,
            'id_profesional_efector_servicio' => $this->id_profesional_efector_servicio,
            'fecha_alta' => $this->fecha_alta,
            'fecha_mod' => $this->fecha_mod,
        ]);

        $claveProf = $this->profesional_clave !== null && $this->profesional_clave !== ''
            ? trim((string) $this->profesional_clave)
            : '';
        if ($claveProf === '' && $this->id_profesional_efector_servicio !== null && $this->id_profesional_efector_servicio !== '') {
            $claveProf = (string) (int) $this->id_profesional_efector_servicio;
        }
        if ($claveProf !== '') {
            if ($claveProf[0] === 'p' || $claveProf[0] === 'P') {
                $idPesF = (int) substr($claveProf, 1);
                if ($idPesF > 0) {
                    $query->andWhere(['id_profesional_efector_servicio' => $idPesF]);
                }
            } else {
                $idLeg = (int) $claveProf;
                if ($idLeg > 0) {
                    $pesByPk = ProfesionalEfectorServicio::findOne(['id' => $idLeg, 'deleted_at' => null]);
                    if ($pesByPk !== null) {
                        $query->andWhere(['id_profesional_efector_servicio' => $idLeg]);
                    }
                }
            }
        }

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

        } elseif ($this->dni == '' && $this->busqueda_libre) {
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
        $query = Turno::find();
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromIdRrhh((int) $idRrhh);
        $idEfectorSesion = (int) Yii::$app->user->getIdEfector();
        if ($idPersona === null || $idPersona <= 0 || $idEfectorSesion <= 0) {
            $query->where('0=1');

            return $dataProvider;
        }

        $idsServicios = ProfesionalEfectorServicio::find()
            ->select(['id_servicio'])
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfectorSesion,
                'deleted_at' => null,
            ])
            ->column();

        // Traigo los servicios que podrian requerir pasar por el servicio actual del rrhh
        $serviciosConPasePrevio = $idsServicios !== []
            ? ServiciosEfector::find()
                ->andWhere(['servicios_efector.id_efector' => Yii::$app->user->getIdEfector()])
                ->andWhere(['in', 'servicios_efector.pase_previo', $idsServicios])
                ->all()
            : [];
        $idServiciosConPasePrevio = \Yii\helpers\ArrayHelper::getColumn($serviciosConPasePrevio, 'id_servicio');

        $totalIdsServicios = array_unique(array_merge($idsServicios, $idServiciosConPasePrevio));

        if (!$this->validate()) {
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id_turnos' => $this->id_turnos,
            'id_persona' => $this->id_persona,
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'id_consulta_referencia' => $this->id_consulta_referencia,
            'id_servicio_asignado' => $this->id_servicio_asignado,
            'id_profesional_efector_servicio' => $this->id_profesional_efector_servicio,
            'fecha_alta' => $this->fecha_alta,
            'fecha_mod' => $this->fecha_mod,
        ]);

        $claveProf = $this->profesional_clave !== null && $this->profesional_clave !== ''
            ? trim((string) $this->profesional_clave)
            : '';
        if ($claveProf === '' && $this->id_profesional_efector_servicio !== null && $this->id_profesional_efector_servicio !== '') {
            $claveProf = (string) (int) $this->id_profesional_efector_servicio;
        }
        if ($claveProf !== '') {
            if ($claveProf[0] === 'p' || $claveProf[0] === 'P') {
                $idPesF = (int) substr($claveProf, 1);
                if ($idPesF > 0) {
                    $query->andWhere(['id_profesional_efector_servicio' => $idPesF]);
                }
            } else {
                $idLeg = (int) $claveProf;
                if ($idLeg > 0) {
                    $pesByPk = ProfesionalEfectorServicio::findOne(['id' => $idLeg, 'deleted_at' => null]);
                    if ($pesByPk !== null) {
                        $query->andWhere(['id_profesional_efector_servicio' => $idLeg]);
                    }
                }
            }
        }


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

        $query->andFilterWhere(['id_efector' => Yii::$app->user->getIdEfector()])
            ->orderBy('fecha', 'hora');

        return $dataProvider;
    }
}
