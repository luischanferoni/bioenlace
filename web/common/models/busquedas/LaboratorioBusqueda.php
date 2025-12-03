<?php

namespace common\models\busquedas;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Query;

use common\models\file\DengueImport;
use common\models\file\VirusRespiratoriosImport;

/**
 * LaboratorioBusqueda represents the model behind the search form about `common\models\file\DengueImport`.
 */
class LaboratorioBusqueda extends DengueImport {

    const SCENARIO_ACCESOLIBRE = 'accesolibre';
    
    const TIPOS_ESTUDIOS_DENGUE = 'dengue';
    const TIPOS_ESTUDIOS_VIRUS_RESPIRATORIO = 'virus_respiratorio';

    const TIPOS_ESTUDIOS = [self::TIPOS_ESTUDIOS_DENGUE => 'Dengue', self::TIPOS_ESTUDIOS_VIRUS_RESPIRATORIO => 'Virus Respiratorio'];
    
    public $tipo_estudio;
    public $codigoVerificacion;

    // para filtrar las fechas
    public $rango_fechas_recepcion;
    public $rango_fechas_procesamiento;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['tipo_estudio', 'dni'], 'required', 'on' => self::SCENARIO_ACCESOLIBRE],
            ['codigoVerificacion', 'captcha', 'on' => self::SCENARIO_ACCESOLIBRE],
            [['rango_fechas_recepcion', 'rango_fechas_procesamiento'], 'string'],
            ['tipo_estudio', 'in', 'range' => array_keys(self::TIPOS_ESTUDIOS)],
            [['dni'], 'integer'],            
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
        if(!$this->load($params)) {
            $dataProvider = new ActiveDataProvider();
            $dataProvider->setTotalCount(-1);
            return $dataProvider;
        }

        switch ($this->tipo_estudio) {
            case 'dengue':
                $query = DengueImport::find();
                break;
            case 'virus_respiratorio':
                $query = VirusRespiratoriosImport::find();
                break;
            default:
                $query = DengueImport::find();
                break;
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => false,
        ]);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            //return $dataProvider;
        }

        $query->andFilterWhere([
            'dni' => $this->dni,
        ]);

        $session = Yii::$app->session;
        $session->remove('ids_laboratorio');
        // al momento de la descarga, no la habilitamos si el usuario no ha pasado antes por aqui
        if ($dataProvider->getTotalCount() > 0) {
            foreach($dataProvider->models as $model) {
                $ids_dengue[] = $model->id;
            }
            $session->set('ids_laboratorio', $ids_dengue);  
        }

        $query->orderBy([
            'fecha_procesamiento' => SORT_DESC,            
        ]);

        return $dataProvider;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function reporte($params)
    {
        $this->load($params);

        switch ($this->tipo_estudio) {
            case 'dengue':
                $query = DengueImport::find();
                break;
            case 'virus_respiratorio':
                $query = VirusRespiratoriosImport::find();
                break;
            default:
                $query = DengueImport::find();
                break;
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => false,
        ]);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            $query->where('0=1');
            //return $dataProvider;
        }

        $query->andFilterWhere([
            'dni' => $this->dni,
        ]);

        if (!is_null($this->rango_fechas_recepcion) && strpos($this->rango_fechas_recepcion, ' - ') !== false ) {
            list($start_date, $end_date) = explode(' - ', $this->rango_fechas_recepcion);
            $query->andFilterWhere(['between', 'fecha_recepcion', $start_date, $end_date]);
        }

        if (!is_null($this->rango_fechas_procesamiento) && strpos($this->rango_fechas_procesamiento, ' - ') !== false ) {
            list($start_date, $end_date) = explode(' - ', $this->rango_fechas_procesamiento);
            $query->andFilterWhere(['between', 'fecha_procesamiento', $start_date, $end_date]);
        }

        $query->orderBy([
            'fecha_procesamiento' => SORT_DESC,            
        ]);

        return $dataProvider;
    }    
}
