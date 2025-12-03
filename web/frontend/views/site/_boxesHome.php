<?php
use common\models\Consulta;
?>

<div class="row">
    <?php 

        //Obtenemos de la sesion el encounter class, para ver que botones mostrar en el site/index


        echo $this->render('_boxBusquedaPersona', []);
        
        switch(Yii::$app->user->getEncounterClass()){
            case Consulta::ENCOUNTER_CLASS_AMB:
                echo $this->render('_boxAtencion', ['turnos' => $items]);
            break;

            case Consulta::ENCOUNTER_CLASS_IMP:
                if (isset($items) && count($items) > 0) {
                    echo $this->render('_boxInternacion', ['internados' => $items]);
                }
            break;

            case Consulta::ENCOUNTER_CLASS_EMER:
                echo $this->render('_boxGuardia', ['guardias' => $items]); //aqui deberiamos mandar el listado de espera de guardia
            break;

        }

        echo $this->render('_boxNovedades', ['novedades' => $novedades]);

    ?>

</div>

