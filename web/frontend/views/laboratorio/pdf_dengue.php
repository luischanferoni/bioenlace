<?php

use yii\widgets\DetailView;

?>

<div>
    <img style="margin-right: 15px" width="24%" src="<?php echo Yii::getAlias('@web') . "/images/rsz_logo_ceamm.png"; ?>" />    
    <b style="font-size: 16px">ÁREA DENGUE Y OTROS ARBOVIRUS</b>
    <img style="margin-left: 25px;" width="24%" alt="logo_ ministerio de desarrollo social" src="<?php echo Yii::getAlias('@web') . "/images/rsz_logo_ministerio_salud.png"; ?>" />    
</div>

<br />

<table>
    <tr>
        <td style="width: 55%">
        <?php
            echo DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'codigo',
                    'apellido',
                    'nombre',
                    'dni',
                    'edad',
                    'establecimiento_notificador',
                    'centro_derivador',
                    'localidad',
                    'departamento',
                ],
                'template' => "<tr><th style='width: 45%;padding:5px 0 5px 5px'>{label}</th><td style='padding:5px 0 5px 5px'>{value}</td></tr>"
            ]);
        ?>
        </td>
        <td style="vertical-align:top">
        <?php
            echo DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'fecha_inicio_fiebre:date',
                    'fecha_recepcion:date',
                    'fecha_procesamiento:date',
                    'dias_evolucion',
                ],
                'template' => "<tr><th style='width: 60%;padding:5px 0 5px 5px'>{label}</th><td style='padding:5px 0 5px 5px'>{value}</td></tr>"
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
            'ns1_elisa',
            'ns1_test_rapido',           
            'ig_m_dengue_elisa',
            'ig_m_test_rapido',
            'igg_test_rapido',
            'igm_chik',            
            'serotipo_virus_dengue',
            'igg_test_rapido',
            'rt_pcr_chik',
            'rt_pcr_tiempo_real_dengue',
            'rt_pcr_tiempo_real_chik',
            'rt_pcr_tiempo_real_zika',
            'rt_pcr_tiempo_real_yf',            
            'resultado_laboratorio',
            'observaciones',
        ],
        'template' => "<tr><th style='width: 30%;padding:5px 0 5px 5px'>{label}</th><td style='padding:5px 0 5px 5px'>{value}</td></tr>"
    ]);
?>

<br />
<br />
<br />
<table style="width: 100%">
    <tr>
        <td style="width: 70%">
        </td>
        <td style="width: 30%">
        Dr. Marcelo A. Ovejero<br>
        Bioquímico MP 374            
        </td>
    </tr>
</table>
