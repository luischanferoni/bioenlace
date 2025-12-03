<?php

namespace common\models\busquedas;

use common\models\Persona;
use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Turno;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;

/**
 * TurnoBusqueda represents the model behind the search form about `common\models\Turno`.
 */
class TurnoLibreBusqueda extends Turno
{
    public $dni;
    public $codigoVerificacion;
    public $fecha_hoy;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['dni'], 'required'],
            [['dni'], 'string'],
            [['fecha_hoy'],'safe'],
            [['codigoVerificacion'], 'captcha'],

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

        if(!$this->load($params)) {
            $dataProvider = new ActiveDataProvider();
            $dataProvider->setTotalCount(-1);
            return $dataProvider;
        }

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }


        if ($this->dni != '') {

            $persona = Persona::findOne(['documento' => $this->dni]);

            if($persona){

                $query->andFilterWhere(['id_persona' => $persona->id_persona])
                ->andFilterWhere(['<>', 'estado', Turno::ESTADO_ATENDIDO])
                ->andFilterWhere(['>=', 'fecha', $this->fecha_hoy])
                ->orderBy('id_turnos ASC');

            }else{

                $query->andWhere(['is', 'id_persona', null]);

            }


        } else {
            $dataProvider = new ActiveDataProvider();
            $dataProvider->setTotalCount(-1);
            return $dataProvider;
        }

        return $dataProvider;
    }
}
