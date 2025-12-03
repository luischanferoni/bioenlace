<?php

namespace frontend\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\widgets\ActiveForm;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

use kartik\mpdf\Pdf;

use common\models\file\CSVForm;
use common\models\file\DengueImport;
use common\models\file\VirusRespiratoriosImport;
use common\models\busquedas\LaboratorioBusqueda;
use common\models\Laboratorio;
use common\models\Persona;

/**
 * LaboratorioController implements the CRUD actions for LaboratorioController model.
 */
class LaboratorioController extends Controller
{
    public $freeAccessActions = ['busqueda', 'seleccionar-descarga', 'descargar'];

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'ghost-access' => [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'descargar' => ['POST'],
                ],
            ],
            /*'rateLimiter' => [
                'class' => 'thamtech\ratelimiter\RateLimiter',
                'only' => ['busqueda'],
                'components' => [
                    'rateLimit' => [
                        'definitions' => [
                            'ip' => [
                                'limit' => 50,
                                'window' => 3600,

                                'identifier' => function($context, $rateLimitId) {
                                    return $context->request->getUserIP();
                                },
                                'active' => Yii::$app->user->isGuest,
                            ],                        
                        ],
                    ],
                    'allowanceStorage' => [
                        'cache' => 'cache',
                    ],
                ],
                
                'as tooManyRequestsException' => [ 
                    'class' => 'thamtech\ratelimiter\handlers\TooManyRequestsHttpExceptionHandler',
                    'message' => 'Excedio el limite de solicitudes, consulte mas tarde.',
                ],
            ],*/
        ];
    }

    // Busqueda interna, sin el captcha
    public function actionReporteDengue() 
    {
        $searchModel = new LaboratorioBusqueda();
        $searchModel->tipo_estudio = LaboratorioBusqueda::TIPOS_ESTUDIOS_DENGUE;
        $dataProvider = $searchModel->reporte(Yii::$app->request->queryParams);        

        return $this->render('reporte_dengue', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

    public function actionReporteVirusRespiratorios() 
    {
        $searchModel = new LaboratorioBusqueda();
        $searchModel->tipo_estudio = LaboratorioBusqueda::TIPOS_ESTUDIOS_VIRUS_RESPIRATORIO;
        $dataProvider = $searchModel->reporte(Yii::$app->request->queryParams);

        return $this->render('reporte_virus_respiratorios', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }


    // Busqueda publica
    public function actionBusqueda()
    {
        $this->layout = 'publico/main';
        
        $searchModel = new LaboratorioBusqueda();
        $searchModel->scenario = LaboratorioBusqueda::SCENARIO_ACCESOLIBRE;

        $dataProvider = $searchModel->search(Yii::$app->request->post());

        return $this->render('index', [
                    'accesolibre' => true,
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

    public function actionSeleccionarDescarga() 
    {
        $id = Yii::$app->request->post('id');
        $model = DengueImport::find()->where(['id' => $id])->one();
        if(!$model) {
            throw new NotFoundHttpException("No se encontró el registro.");
        }

        $session = Yii::$app->session;
        $session->set('id_dengue', $id);        
    }

    public function actionDescargar()
    {
        $id = Yii::$app->request->post('id');
        $tipo = Yii::$app->request->post('tipo');
        
        // ids_laboratorio se setea al momento de la busqueda para hacer un control de que el usuario
        // haya pasado por la busqueda antes
        $session = Yii::$app->session;
        $ids = $session->get('ids_laboratorio');
        
        if(is_null($ids) || !in_array($id, $ids)) {
            throw new NotFoundHttpException("No se encontró el registro.");
        }

        switch ($tipo) {
            case LaboratorioBusqueda::TIPOS_ESTUDIOS_DENGUE:
                $claseDeImportacion = DengueImport::classname();
                break;
            case LaboratorioBusqueda::TIPOS_ESTUDIOS_VIRUS_RESPIRATORIO:
                $claseDeImportacion = VirusRespiratoriosImport::classname();
                break;
        }
//var_dump($tipo);die;
        $model = $claseDeImportacion::find()->where(['id' => $id])->one();
        if(!$model) {
            throw new NotFoundHttpException("No se encontró el registro.");
        }

        $content = $this->renderPartial('pdf_'.$tipo, ['model' => $model]);
        setlocale(LC_ALL, 'es_AR.utf8');
        $pdf = new Pdf([
            'filename' => $tipo.'_'.$model->{$claseDeImportacion::UNIQUE}.'.pdf',
            // set to use core fonts only
            'mode' => Pdf::MODE_CORE, 
            // A4 paper format
            'format' => Pdf::FORMAT_A4, 
            // portrait orientation
            'orientation' => Pdf::ORIENT_PORTRAIT, 
            // stream to browser inline
            'destination' => Pdf::DEST_BROWSER, 
            // your html content input
            'content' => $content,  
            // format content from your own css file if needed or use the
            // enhanced bootstrap css built by Krajee for mPDF formatting 
            //'cssFile' => '@vendor/kartik-v/yii2-mpdf/src/assets/kv-mpdf-bootstrap.min.css',
            // any css to be embedded if required
            'cssInline' => '.left{float: left;}.right{float: right;}', 
             // set mPDF properties on the fly
            'options' => ['title' => 'Resultados de Laboratorio'],
             // call mPDF methods on the fly
            'methods' => [ 
                'SetFooter'=>[strftime("%d de %B %G, %H:%M HS")],
            ]
        ]);
        //date('F jS, Y h:i', time())
        // return the pdf output as per the destination setting
        return $pdf->render();        
    }

    /**
     * Para ver el listado de columnas y el orden en el que se acepta el CSV
     * revisar el/los modelos de models/file
    */
    /*
    public function actionImportar()
    {
        $model = new CSVForm;
        
        $procesados = null;

        if (!$model->load(Yii::$app->request->post())) {
            return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => 'Dengue']);
        }

        $file = UploadedFile::getInstance($model, 'archivo');
        $filename = uniqid('dengue_').'.'. $file->extension;
        $upload = $file->saveAs('uploads/' . $filename);

        if (!$upload) {
            return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => 'Dengue']);
        }

        ini_set('auto_detect_line_endings', TRUE);
        define('CSV_PATH', 'uploads/');
        $csv_file = CSV_PATH . $filename;

        if (($readHandle = fopen($csv_file, "r")) === false) {
            ini_set('auto_detect_line_endings', FALSE);
            return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => 'Dengue']);
        }
        
        // para no procesar los headers
        $headers = fgetcsv($readHandle, 4096, ";");

        // dentro de este while hacemos un match entre las columas y las propiedades del modelo
        $procesados['exitosos'] = 0;
        $procesados['errores'] = [];
        $procesados['ya_procesados'] = [];
        $row_i = 1;
        while($row = fgetcsv($readHandle, 4096, ";")) {
            $dengue = new DengueImport;
            foreach (DengueImport::ATTRIBUTES as $k => $attribute) {                
                $dengue->$attribute = isset($row[$k]) ? trim($row[$k]) : '';
            }
            $dengue->archivo = $file->getBaseName();

            if (!$dengue->save()) {
                if (array_key_exists('codigo', $dengue->getErrors()) && strpos($dengue->getErrors()['codigo'][0], 'ya ha sido utilizado')) {
                    $procesados['ya_procesados'][] = ['model' => $dengue, 'row' => $row_i];
                } else {
                    $procesados['errores'][] = ['model' => $dengue, 'row' => $row_i];
                }
            } else {
                $procesados['exitosos'] ++;
            }
            $row_i ++;
        }

        ini_set('auto_detect_line_endings', FALSE);        
            
        return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => 'Dengue']);
    }
*/
    public function importarV2($claseDeImportacion)
    {
        $model = new CSVForm;
        
        $procesados = null;

        if (!$model->load(Yii::$app->request->post())) {
            return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => $claseDeImportacion::DESCRIPCION]);
        }

        $file = UploadedFile::getInstance($model, 'archivo');
        $filename = uniqid($claseDeImportacion::SUFIJO_NOMBRE_ARCHIVO).'.'. $file->extension;
        $upload = $file->saveAs('uploads/' . $filename);

        if (!$upload) {
            $model->addError('archivo', 'Fallo la subida del archivo');
            return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => $claseDeImportacion::DESCRIPCION]);
        }

        ini_set('auto_detect_line_endings', TRUE);
        define('CSV_PATH', 'uploads/');
        $csv_file = CSV_PATH . $filename;

        if (($readHandle = fopen($csv_file, "r")) === false) {
            ini_set('auto_detect_line_endings', FALSE);
            $model->addError('archivo', 'No se pudo abrir el archivo');
            return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => $claseDeImportacion::DESCRIPCION]);
        }
        
        // para no procesar los headers
        $headers = fgetcsv($readHandle, 4096, ";");
        if (count($headers) == 1) {
            $model->addError('archivo', 'Al parecer la delimitacion del CSV no es punto y coma (;)');
            return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => $claseDeImportacion::DESCRIPCION]);
        }

        // dentro de este while hacemos un match entre las columas y las propiedades del modelo
        $procesados['exitosos'] = 0;
        $procesados['errores'] = [];
        $procesados['ya_procesados'] = [];
        $row_i = 2;
        
        while($row = fgetcsv($readHandle, 4096, ";")) {
            $modelAImportar = new $claseDeImportacion;
            $codigo = '';
            foreach ($claseDeImportacion::ATTRIBUTES as $k => $attribute) {               
                /*if ($codigo ==  '34763' && $attribute == 'localidad') {
                    var_dump($row[$k]);
                    var_dump(mb_detect_encoding($row[$k]));die;
                }*/
                $modelAImportar->$attribute = isset($row[$k]) ? trim($row[$k]) : '';

                if (mb_detect_encoding($modelAImportar->$attribute) == 'UTF-8') {
                    $modelAImportar->$attribute = mb_convert_encoding($modelAImportar->$attribute, 'UTF-8', 'ISO-8859-1');
                } else {
                    if (!mb_detect_encoding($modelAImportar->$attribute)) {
                        $modelAImportar->$attribute = mb_convert_encoding($modelAImportar->$attribute, 'UTF-8', 'ISO-8859-1');
                    }
                }
               /* if ($attribute == 'codigo' && $modelAImportar->$attribute == '34763') {
                    $codigo = '34763';
                }*/
            }

            $modelAImportar->archivo = $file->getBaseName();

            if (!$modelAImportar->save()) {
                if (array_key_exists($claseDeImportacion::UNIQUE, $modelAImportar->getErrors()) && 
                        strpos($modelAImportar->getErrors()[$claseDeImportacion::UNIQUE][0], 'ya ha sido utilizado')) {
                    $procesados['ya_procesados'][] = ['model' => $modelAImportar, 'row' => $row_i];
                } else {
                    $procesados['errores'][] = ['model' => $modelAImportar, 'row' => $row_i];
                }
            } else {
                $procesados['exitosos'] ++;
            }

            $row_i ++;
        }

        ini_set('auto_detect_line_endings', FALSE);        
            
        return $this->render('importar', ['model' => $model, 'procesados' => $procesados, 'title' => $claseDeImportacion::DESCRIPCION]);        
    }

    public function actionImportarDengue()
    {
        return $this->importarV2(DengueImport::classname());
    }

    public function actionImportarVirusRespiratorios()
    {
        return $this->importarV2(VirusRespiratoriosImport::classname());
    }    
}