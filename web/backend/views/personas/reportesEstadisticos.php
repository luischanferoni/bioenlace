<?php
/**
 * Vista donde se encuentra embebido el menu para el acceso a los reportes estadisticos *  
 * @autor: María de los A. Valdez
 * @versión: 1.0 
 * @creacion: 1  /03/2017
 * @modificacion:
 **/

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = '';
$this->params['breadcrumbs'][] = $this->title;

?>

<div>

          <?php
          
         //  $id_user = Yii::$app->user->id;
            //5 $id_efector = Yii::$app->user->getIdEfector();
           
         //  echo "ID EFECTOR".$id_efector;
         //  echo "ID USER".$id_user;          

        $reportico = \Yii::$app->getModule('reportico');
        $engine = $reportico->getReporticoEngine();        // Fetches reportico engine
        $engine->access_mode = "ONEPROJECT";              
        $engine->initial_execute_mode = "MENU";  
        $engine->initial_project = "Reportes Estadisticos BIOENLACE";            // Name of report project folder    
        $engine->initial_report = "reporteC2Individual";           // Name of report to run
        $engine->bootstrap_styles = "3";                   // Set to "3" for bootstrap v3, "2" for V2 or false for no bootstrap
        $engine->force_reportico_mini_maintains = true;    // Often required
        $engine->bootstrap_preloaded = true;               // true if you dont need Reportico to load its own bootstrap
        $engine->clear_reportico_session = true;   
        $engine->embedded_report = true;// Normally required     
        //$engine->user_parameters["idEfector"] = 786;  
        //$engine->external_user = $id_efector;  
          
                  
        // Turn on and off UI elements
        $engine->output_template_parameters["show_hide_navigation_menu"] = "show";   
        $engine->output_template_parameters["show_hide_dropdown_menu"] = "show";
        $engine->output_template_parameters["show_hide_report_output_title"] = "show";
        $engine->output_template_parameters["show_hide_prepare_section_boxes"] = "hide";
        $engine->output_template_parameters["show_hide_prepare_pdf_button"] = "show";
        $engine->output_template_parameters["show_hide_prepare_html_button"] = "show";
        $engine->output_template_parameters["show_hide_prepare_print_html_button"] = "hide";
        $engine->output_template_parameters["show_hide_prepare_csv_button"] = "hide";
        $engine->output_template_parameters["show_hide_prepare_page_style"] = "show";

        $engine->execute();   

          ?> 
         
</div>