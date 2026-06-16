<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Personas';
$this->params['breadcrumbs'][] = $this->title;
//print_r($dataProvider);
?>
<style>

    @page normal { size : landscape }
    table { page-break-before : always }
    @media print {

        body { font-size: 10pt }
        .swLinkMenu { display: none; }
        .swRepBody { width: 100%; margin: 0; border: none; }
        .swRepTitle { font-size: 18pt; text-align: center; margin-top: 0px; border: none;}
        .swMntForm { text-align: left; margin-left: 0%; background-color: #ffffff; border: none; }
        @page { margin-top: 200; padding-top : 100px; }
    }
</style>
<div class="persona-index">

    <?php
    $reportico = \Yii::$app->getModule('reportico');
    $engine = $reportico->getReporticoEngine();        // Fetches reportico engine
//        $engine->access_mode = "REPORTOUTPUT";             // Allows access to report output only
//        $engine->initial_execute_mode = "EXECUTE";         // Just executes specified report
    $engine->access_mode = "ONEPROJECT";               // Allows access to all Reportico pages
    $engine->initial_execute_mode = "MENU";            // Starts user in administration page
    $engine->initial_project = "Proyecto Reportes BIOENLACE";            // Name of report project folder    
    $engine->initial_report = "historiasclinicasxupa";           // Name of report to run
    $engine->bootstrap_styles = "3";                   // Set to "3" for bootstrap v3, "2" for V2 or false for no bootstrap
    $engine->force_reportico_mini_maintains = true;    // Often required
    $engine->bootstrap_preloaded = true;               // true if you dont need Reportico to load its own bootstrap
    $engine->clear_reportico_session = true;           // Normally required     
//        $engine->user_parameters["idEfector"] = 786;
    $id_efector = Yii::$app->user->idEfector;
    $engine->external_user = $id_efector;
    $engine->execute();
    ?> 

</div>