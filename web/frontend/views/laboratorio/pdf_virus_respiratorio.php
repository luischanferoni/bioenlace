<?php

use yii\widgets\DetailView;

?>

<div>
    <img style="margin-right: 155px" width="24%" src="<?php echo Yii::getAlias('@web') . "/images/rsz_logo_ceamm.png"; ?>" />        
    <img style="margin-left: 155px;" width="24%" alt="logo_ ministerio de desarrollo social" src="<?php echo Yii::getAlias('@web') . "/images/rsz_logo_ministerio_salud.png"; ?>" />
    <br>
    <div style="margin-left: 110px;">
        <b style="font-size: 16px">DIAGNÓSTICO MOLECULAR DE VIRUS RESPIRATORIOS</b>
    </div>
</div>

<br />

<table>
    <tr>
        <td>
            <?php
                echo DetailView::widget([
                    'model' => $model,
                    'attributes' => [
                        'caso',
                        'apellido',
                        'nombre',
                        'dni',
                        'edad',
                        'establecimiento_notificador',
                        'localidad',
                        'situacion_paciente',
                        'fecha_procesamiento:date',
                        'tipo_muestra',
                        'observaciones',
                    ],
                    'template' => "<tr><th style='width: 65%;padding:5px 0 5px 5px'>{label}</th><td style='padding:5px 0 5px 5px'>{value}</td></tr>"
                ]);
            ?>
        </td>
    </tr>
</table>


<b style="color: #6a74dd; text-decoration:underline; font-size: 18px">RESULTADOS DE LABORATORIO:</b>
<br />
<?php
    echo DetailView::widget([
        'model' => $model,
        'attributes' => [
            'resultado_genoma_viral_sars_cov_2',
            'resultado_rt_pcr_virus_influenza_a',           
            'resultado_rt_pcr_virus_influenza_b',
            'resultado_genoma_viral_rsv',
        ],
        'template' => "<tr><th style='width: 40%;padding:5px 0 5px 5px'>{label}</th><td style='padding:5px 0 5px 5px'>{value}</td></tr>"
    ]);
?>

<br />
<br />
<br />
<table style="width: 100%">
    <tr>
        <td>
            Metodología: Detección mediante PCR en tiempo real de secuencias específicas correspondientes al genoma viral de: SARS-CoV-2, FLU-A, FLU-B y RSV
        </td>
    </tr>
</table>
