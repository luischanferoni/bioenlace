<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\models\Referencia;
use webvimark\modules\UserManagement\models\User;

/**
 * ReferenciasBusquedas represents the model behind the search form about `common\models\Referencia`.
 */
class ReferenciasBusquedas extends Referencia
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id_referencia', 'id_consulta', 'id_efector_referenciado', 'id_motivo_derivacion', 'id_servicio', 'id_estado'], 'integer'],
            [['estudios_complementarios', 'resumen_hc', 'tratamiento_previo', 'tratamiento', 'fecha_turno', 'hora_turno', 'observacion'], 'safe'],
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
     * Si el Rol del usuario es "Medico" se muestran las referencias creadas por
     * dicho médico en el efector con el cual esta logeado
     * Si el Rol del usuario es "Administrativo" se muestran las referencias creadas en
     * el efector en el cual esta logeado 
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {

        $id_user = Yii::$app->user->id;
        $id_efector = Yii::$app->user->idEfector;
        $rr_hh = \common\models\Persona::find()->select(['rr_hh.id_rr_hh'])
                ->from('personas')
                ->join('INNER JOIN','rr_hh','personas.id_persona=rr_hh.id_persona')
                ->where(['personas.id_user' => $id_user]);
        
        $esAdministrativo = User::hasRole(['Administrativo'], $superAdminAllowed = true);
        $esMedico = User::hasRole(['Medico'], $superAdminAllowed = true);
        if ($esMedico) {           
            $query = referencia::find()
                    -> select
                    (['id_referencia' => 'referencia.id_referencia',
                      'id_consulta' => 'consultas.id_consulta'  , 
                      'id_efector_referenciado' => 'referencia.id_efector_referenciado',
                      'id_motivo_derivacion' => 'referencia.id_motivo_derivacion',
                      'id_servicio' => 'referencia.id_servicio',
                      'id_estado' => 'referencia.id_estado',
                      'fecha_turno' => 'referencia.fecha_turno',
                      'hora_turno' => 'referencia.hora_turno',
                            ])
                    ->from('referencia')
                    ->join('INNER JOIN','consultas','referencia.id_consulta=consultas.id_consulta')
                    ->join('INNER JOIN','turnos','consultas.id_turnos=turnos.id_turnos')
                    ->join('INNER JOIN','rr_hh','turnos.id_rr_hh=rr_hh.id_rr_hh')
                    ->join('INNER JOIN','personas','rr_hh.id_persona=personas.id_persona')
                    ->join('INNER JOIN','efectores','turnos.id_efector=efectores.id_efector')
                    ->where(['turnos.id_efector' => $id_efector])
                    ->andWhere(['turnos.id_rr_hh' => $rr_hh ]);
        } else {
           $query = referencia::find()
                    -> select
                    (['id_referencia' => 'referencia.id_referencia',
                      'id_consulta' => 'consultas.id_consulta'  , 
                      'id_efector_referenciado' => 'referencia.id_efector_referenciado',
                      'id_motivo_derivacion' => 'referencia.id_motivo_derivacion',
                      'id_servicio' => 'referencia.id_servicio',
                      'id_estado' => 'referencia.id_estado',
                      'fecha_turno' => 'referencia.fecha_turno',
                      'hora_turno' => 'referencia.hora_turno',
                            ])
                    ->from('referencia')
                    ->join('INNER JOIN','consultas','referencia.id_consulta=consultas.id_consulta')
                    ->join('INNER JOIN','turnos','consultas.id_turnos=turnos.id_turnos')
                    ->join('INNER JOIN','rr_hh','turnos.id_rr_hh=rr_hh.id_rr_hh')
                    ->join('INNER JOIN','personas','rr_hh.id_persona=personas.id_persona')
                    ->join('INNER JOIN','efectores','turnos.id_efector=efectores.id_efector')
                    ->where(['turnos.id_efector' => $id_efector]);
        }
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
            'id_referencia' => $this->id_referencia,
            'id_consulta' => $this->id_consulta,
            'id_efector_referenciado' => $this->id_efector_referenciado,
            'id_motivo_derivacion' => $this->id_motivo_derivacion,
            'id_servicio' => $this->id_servicio,
            'id_estado' => $this->id_estado,
            'fecha_turno' => $this->fecha_turno,
            'hora_turno' => $this->hora_turno,
        ]);

        $query->andFilterWhere(['like', 'estudios_complementarios', $this->estudios_complementarios])
            ->andFilterWhere(['like', 'resumen_hc', $this->resumen_hc])
            ->andFilterWhere(['like', 'tratamiento_previo', $this->tratamiento_previo])
            ->andFilterWhere(['like', 'tratamiento', $this->tratamiento])
            ->andFilterWhere(['like', 'observacion', $this->observacion]);

        return $dataProvider;
    }
}
