<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Persona;

/**
 * PersonaBusqueda represents the model behind the search form about `common\models\persona`.
 */
class PersonaBusqueda extends persona
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_persona', 'id_tipodoc', 'id_estado_civil'], 'integer'],
            [['apellido', 'nombre', 'documento', 'sexo', 'fecha_nacimiento', 'fecha_defuncion', 'usuario_alta', 'fecha_alta', 'usuario_mod', 'fecha_mod'], 'safe'],
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
        $query = persona::findActive();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        // this is to return an empty search result by default
        if(!isset($params['PersonaBusqueda'])) {
            $query->where('0=1');
            return $dataProvider;
        }

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id_persona' => $this->id_persona,
            'id_tipodoc' => $this->id_tipodoc,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'id_estado_civil' => $this->id_estado_civil,
            'fecha_defuncion' => $this->fecha_defuncion,
            'fecha_alta' => $this->fecha_alta,
            'fecha_mod' => $this->fecha_mod,
        ]);

        $query->andFilterWhere(['like', 'apellido', $this->apellido])
            ->andFilterWhere(['like', 'nombre', $this->nombre])
            ->andFilterWhere(['like', 'documento', $this->documento])
            ->andFilterWhere(['like', 'sexo', $this->sexo])
            ->andFilterWhere(['like', 'usuario_alta', $this->usuario_alta])
            ->andFilterWhere(['like', 'usuario_mod', $this->usuario_mod]);

        return $dataProvider;
    }

    /**
     * Data Provider para el listado PERSONAS-RRHH
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function searchpersonarrhh($params) {

        //consulta en la vista personas_rrhh
        $query = Persona::findActive()->select('*')->from('personas_rrhh')
            ->where(['=','id_efector',Yii::$app->user->getIdEfector()])
            ->andWhere(['=','rrhh_eliminado', 0])
            ->asArray();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
}

        $query->andFilterWhere(['like', 'id_persona', $this->id_persona])
                ->andFilterWhere(['like', 'apellido', $this->apellido])
                ->andFilterWhere(['like', 'nombre', $this->nombre]);
        

        return $dataProvider;
    }

}
