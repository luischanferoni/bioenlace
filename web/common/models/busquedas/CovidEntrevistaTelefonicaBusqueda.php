<?php

namespace common\models\busquedas;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\CovidEntrevistaTelefonica;

/**
 * CovidEntrevistaTelefonicaBusqueda represents the model behind the search form of `common\models\CovidEntrevistaTelefonica`.
 */
class CovidEntrevistaTelefonicaBusqueda extends CovidEntrevistaTelefonica
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'convivientes', 'vacunado', 'continua_sintomas', 'falta_aire', 'falta_aire_reposo', 'falta_aire_caminar', 'dolor_pecho', 'taquicardia_palpitaciones', 'perdida_memoria', 'cefalea_dolor_cabeza', 'falta_fuerza', 'dolor_muscular', 'secrecion_rinitis_constante', 'llanto_espontaneo', 'cuesta_salir_casa', 'tristeza_angustia', 'dificultad_realizar_tareas'], 'integer'],
            [['id_persona', 'convivientes_datos', 'resultado', 'telefono_contacto', 'fecha_primera_dosis', 'fecha_segunda_dosis'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
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
        $query = CovidEntrevistaTelefonica::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'convivientes' => $this->convivientes,
            'vacunado' => $this->vacunado,
            'fecha_primera_dosis' => $this->fecha_primera_dosis,
            'fecha_segunda_dosis' => $this->fecha_segunda_dosis,
            'continua_sintomas' => $this->continua_sintomas,
            'falta_aire' => $this->falta_aire,
            'falta_aire_reposo' => $this->falta_aire_reposo,
            'falta_aire_caminar' => $this->falta_aire_caminar,
            'dolor_pecho' => $this->dolor_pecho,
            'taquicardia_palpitaciones' => $this->taquicardia_palpitaciones,
            'perdida_memoria' => $this->perdida_memoria,
            'cefalea_dolor_cabeza' => $this->cefalea_dolor_cabeza,
            'falta_fuerza' => $this->falta_fuerza,
            'dolor_muscular' => $this->dolor_muscular,
            'secrecion_rinitis_constante' => $this->secrecion_rinitis_constante,
            'llanto_espontaneo' => $this->llanto_espontaneo,
            'cuesta_salir_casa' => $this->cuesta_salir_casa,
            'tristeza_angustia' => $this->tristeza_angustia,
            'dificultad_realizar_tareas' => $this->dificultad_realizar_tareas,
        ]);

        $query->andFilterWhere(['like', 'id_persona', $this->id_persona])
            ->andFilterWhere(['like', 'convivientes_datos', $this->convivientes_datos])
            ->andFilterWhere(['like', 'resultado', $this->resultado])
            ->andFilterWhere(['like', 'telefono_contacto', $this->telefono_contacto]);

        return $dataProvider;
    }
}
