
<div>
    <?php
        echo yii\helpers\Html::a(
            'CONSULTA CON IA',
            $urlConsulta,
            [
                'class' => 'btn btn-primary atender',
                'title' => 'IA',
                'style' => 'font-weight:bolder'
            ]
        );    
        echo yii\helpers\Html::a(
            'ATENCIÓN ESPONTÁNEA',
            $urlConsulta,
            [
                'class' => 'btn btn-warning atender',
                'title' => 'Consulta Espontánea',
                'style' => 'font-weight:bolder'
            ]
        );
    ?>
</div>