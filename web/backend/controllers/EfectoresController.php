<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\models\ValidarArchivo; //incluyo el modelo que me permite validar el archivo 
use yii\web\UploadedFile; //incluyo la extensión para cargar el archivo

use common\models\Efector;
use common\models\busquedas\EfectorBusqueda;
use common\models\busquedas\RrhhEfectorBusqueda;

//agregamos el modulo de la extension para el control de acceso
use webvimark\modules\UserManagement\UserManagementModule;

/**
 * 
 * La clase EfectoresController implementa las action que posibilitan la gestión de 
 * efectores de la bd SISSE.
 */
class EfectoresController extends Controller
{
    public function behaviors()
    {
         //control de acceso mediante la extensión
        return [
            'ghost-access' => [
                'class' => 'webvimark\modules\UserManagement\components\GhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Efector models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new EfectorBusqueda();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
    
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            
        ]);
    }

    /**
     * Displays a single Efector model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Muestra los recursos humanos para un Efector.
     * @param integer $id
     * @return mixed
     */
    public function actionRrhh($id)
    {
        $searchModel = new RrhhEfectorBusqueda();
        
        $searchModel->id_efector = $id;
        
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('view_rrhh', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Updates an existing Efector model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id_efector]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Finds the Efector model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Efector the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Efector::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('La p&aacute;gina solicitada no existe.');
        }
    }
    
    /**
     * SubirArchivo renderiza la vista que permite mostrar la
     * interfaz para subir el archivo excel, correspondiente a la tabla 
     * efectores exportada de la bd de SISA. Además verifica que el archivo
     * sea válido.
     */    
    public function actionSubirArchivo($mensaje = NULL,$mensaje_dos = NULL ) {
        
        $model = new ValidarArchivo();  //creo una instancia del modelo para validar el archivo
        $confirmacion = null;  //mensaje que se le mostrará al usuario cuando la subida sea exitosa

        if ($model->load(Yii::$app->request->post())) {
             
             // $model->file = UploadedFile::getInstances($model, 'file');  //Para varios archivos
                $model->file = UploadedFile::getInstance($model, 'file');  //Para un sólo archivo

            if ($model->file && $model->validate()) { 
                /* foreach ($model->file as $file) { //para varios archivos (input multiple)
                    $file->saveAs('archivos/' . $file->baseName . '.' . $file->extension);
                    $msg = "<p><strong class='label label-info'>Enhorabuena, subida realizada con éxito</strong></p>";
                }*/
             $archivo = $model -> file;
             $archivo -> saveAs('archivos/' . $archivo->baseName . '.' . $archivo->extension);
             $confirmacion = "<p><strong class='label label-success'>SU ARCHIVO SE HA CARGADO EXITOSAMENTE</strong></p>";                  
                
            }          
        }
        return $this->render("subir_archivo", ["model" => $model, "confirmacion" => $confirmacion, 
                             "insertados" =>"$mensaje", "desactivados" =>"$mensaje_dos"]);
    }

    
    /**
     * ImportarEfectores permite actualizar la tabla efectores
     * en la bd de SISSE a partir del archivo excel correspondiente a la tabla
     * efectores la bd de SISA, insertando los nuevos registros.
     */    
     public function actionImportarEfectores()
     {        
        $inputFile = 'archivos/establecimientos SISA COMPLETO.xls';
        try {
            //Leo el archivo
            $inputFileType = \PHPExcel_IOFactory::identify($inputFile);
            $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($inputFile);
        } catch (Exception $ex) {
            die('Error al leer el archivo');
        }

        $hoja = $objPHPExcel ->getSheet(0);
        $cantRegistros = $hoja ->getHighestRow();
       // $cantColumnas = $hoja ->getHighestColumn();
        
       /*$reg = 15;
        $regDatos = $hoja->rangeToArray('A' . $reg . ':' . 'M' . $reg, NULL, TRUE, FALSE);
        $dato1 = $regDatos[0][1]; //codigo sisa
        $dato2 = $regDatos[0][2];
        $dato3 = $regDatos[0][3];
        $dato4 = $regDatos[0][4];
        $dato5 = $regDatos[0][5];
        $dato6 = $regDatos[0][6];
        $dato7 = $regDatos[0][7];
        $dato8 = $regDatos[0][8];
        $dato9 = $regDatos[0][9];
        $dato10 = $regDatos[0][10];
        $dato11 = $regDatos[0][11];*/
        
/******************************ALTA AUTOMÁTICA ***************************************/
        
        $mensaje_uno = null;
        
        $registros_insertados= 0; //guardará la cantidad de registros que se inserten en efectores
        $reg = 0;
        $num_reg = 0;
       // $dato_bd = 0;
       // $dato_excel = 0;
        
        $esta_efector = 0; //bandera que se cambia cuando se encuentra en la bd SISSE el efector       
        $id_loc = 0;
        $id_dpto = 0;
        
        $cantRegistros = $cantRegistros - 1; 

        $efectores = Efector:: find() ->all(); //Selecciono todos los registros de la tabla efectores de SISSE
             
        for($reg = 14; $reg <= $cantRegistros; $reg++){ //Para recorrer el excel
            
            $regDatos = $hoja->rangeToArray('A' . $reg . ':' . 'M' . $reg, NULL, TRUE, FALSE);//Selecciono todos los registros del excel
            $esta_efector = 0; 
            
            foreach ($efectores as $efec){ //Recorro la tabla efectores de SISSE                
                if ($regDatos[0][1] == $efec->codigo_sisa) {//hago la comparación de los códigos sisa de ambas tablas
                    $esta_efector = 1;
                   // $dato_bd = $efec->codigo_sisa;
                } else {
                    // $dato_excel = $regDatos[0][1];
                    $num_reg = $reg; //Guardo el num de registro del excel p poder recorrerlo p la inserción
                }
            }
     
            if ($esta_efector == 1) {
                 // $mensaje_uno = "El registro " . $dato_bd. " SE encontró en la bd<br>";
            }
            else{// Por aquí entra, si no se encuentra el registro en la bd sisse
                
                $registros_insertados++;    
            
                 //Tomo los datos del registro a insertar
                $regDatos_dos = $hoja->rangeToArray('A' . $num_reg . ':' . 'M' . $num_reg, NULL, TRUE, FALSE); 
                 
                //Busco por el nombre y el id_provincia 22(Sgo), el id_departamento al que corresponde el efector
                
                $departamentos = \common\models\Departamento::find() 
                                  ->where(['nombre' => $regDatos_dos[0][3]]) 
                                  ->andWhere(['id_provincia'=> 22])->one();
                 
                $id_dpto = $departamentos ->id_departamento; 
                 
                //Busco por el nombre y el id_departamento encontrado, el id_localidad al que corresponde el efector
              
                 $localidades = \common\models\Localidad::find() 
                                  ->where(['nombre' => $regDatos_dos[0][7]]) 
                                  ->andWhere(['id_departamento'=> $id_dpto])->one();
                 
                $id_loc = $localidades ->id_localidad; 
                 
                 //Inserto el nuevo efector en SISSE                    
                $efector = new Efector();
                $efector->id_efector = Null;
                $efector->codigo_sisa = $regDatos_dos[0][1];
                $efector->nombre = $regDatos_dos[0][2];
                $efector->dependencia = $regDatos_dos [0][4];
                $efector->tipologia = $regDatos_dos [0][5];
                $efector->domicilio = $regDatos_dos [0][9];
                $efector->telefono = $regDatos_dos [0][10];
                $efector->origen_financiamiento = $regDatos_dos [0][11];
                $efector->id_localidad = $id_loc;
                $efector->save();
            }                         
                 
        }                  
        $mensaje_uno = "<strong class='label label-success'>SE HAN INSERTADO&nbsp;&nbsp;  " .$registros_insertados. " &nbsp;&nbsp;EFECTORES NUEVOS </strong>";             
       
        /*return $this->render("actualizar_efectores",["registros"=>$cantRegistros, "datoSISSE"=>$dato_bd,  
                               "mje1"=>$mensaje, "dpto"=>$id_dpto, "loc"=>$id_loc]); */
        $this->redirect(["efectores/subir_archivo","mensaje" => $mensaje_uno]);
    }
    
    /**
     * DesactivarEfectores permite actualizar la tabla efectores
     * en la bd de SISSE a partir del archivo excel correspondiente a la tabla
     * efectores la bd de SISA, desactivando los registros que ya no estén en SISA.
     */     
     public function actionDesactivarEfectores()
     {        
        $mensaje_dos = 0;
        $inputFile = 'archivos/establecimientos SISA COMPLETO.xls';
        
        try {
            //Leo el archivo
            $inputFileType = \PHPExcel_IOFactory::identify($inputFile);
            $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($inputFile);
        } catch (Exception $ex) {
            die('Error al leer el archivo');
        }

        $hoja = $objPHPExcel ->getSheet(0);
        $cantRegistros = $hoja ->getHighestRow();
  
        $registros_desactivados = 0; //guardará la cantidad de registros que se den de baja
        
        $reg =0;
        $encontrado = 0;
        $efectores = Efector:: find() ->all(); //Selecciono todos los registros de la tabla efectores de SISSE
        
        foreach ($efectores as $efec){ //Recorro la tabla efectores de SISSE  
                $encontrado = 0; 
               
                for($reg = 14; ($reg <= $cantRegistros) && ($encontrado == 0); $reg++){ //Para recorrer el excel
                   $regDatos = $hoja->rangeToArray('A' . $reg . ':' . 'M' . $reg, NULL, TRUE, FALSE);//Selecciono todos los registros del excel
         
                    if ($regDatos[0][1] == $efec->codigo_sisa) {//hago la comparación de los códigos sisa de ambas tablas
                      $encontrado = 1;
                    
                    }            
                }
                if ($encontrado != 1 && $efec -> estado != "INACTIVO" ){ //Si no lo encuentra       
                        
                       $registros_desactivados++;
                       //Actualizo el estado
                       $efec -> estado = "INACTIVO";  
                       $efec ->save();
                }
        }
        
        $mensaje_dos = "<strong class='label label-success'>SE HAN DESACTIVADO&nbsp;&nbsp;  " .$registros_desactivados.
                       " &nbsp;&nbsp;EFECTORES </strong>";             
         
        $this->redirect(["efectores/subir_archivo","mensaje_dos" => $mensaje_dos]);    
    
   }   
  
}


