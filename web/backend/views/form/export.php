<?php
use yii\data\ArrayDataProvider;
use kartik\export\ExportMenu;
use yii\helpers\Html;

$provider = new ArrayDataProvider([
    'allModels' => $datosForm,
    'pagination' => [
        'pageSize' => 100,
    ],
    'sort' => [
        'attributes' => $preguntasKeys,
    ],
]);

echo '<h1>Formulario: '.$formData[0]['nombre'].'</h1>';

//$rows = $provider->getModels();

echo ExportMenu::widget([
    'filename' => 'Formulario_'.$formData[0]['nombre'],
    'dataProvider' => $provider,
    'columns' => $preguntasKeys,
    'clearBuffers' => true, //optional
    'dropdownOptions' => [
        'label' => 'Exportar Datos ',
        'class' => 'btn btn-success text-white'
    ],
    'exportConfig' => [
        /*ExportMenu::FORMAT_TEXT => false,
        ExportMenu::FORMAT_HTML => false,
        ExportMenu::FORMAT_EXCEL => false,
        ExportMenu::FORMAT_PDF => [
            'pdfConfig' => [
                'methods' => [
                    'SetTitle' => 'Grid Export - Krajee.com',
                    'SetSubject' => 'Generating PDF files via yii2-export extension has never been easy',
                    'SetHeader' => ['Krajee Library Export||Generated On: ' . date("r")],
                    'SetFooter' => ['|Page {PAGENO}|'],
                    'SetAuthor' => 'Kartik Visweswaran',
                    'SetCreator' => 'Kartik Visweswaran',
                    'SetKeywords' => 'Krajee, Yii2, Export, PDF, MPDF, Output, GridView, Grid, yii2-grid, yii2-mpdf, yii2-export',
                ]
            ]
        ],*/
    ],
]);
echo '<div style="overflow-y: scroll;">';
echo yii\grid\GridView::widget([
    'dataProvider' => $provider,
    'columns' => $preguntasKeys,
]);
echo '</div>';

?>
               