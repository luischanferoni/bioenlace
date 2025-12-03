<?php

use common\models\ConsultasConfiguracion;

?>
<div class="card">
    <div class="card-body">
        <div class="card-header">
            <h3>Seleccione el Área en donde trabajará</h3>
        </div>

        <div>
            <div class="d-flex flex-column flex-md-row p-4 gap-4 py-md-5 align-items-center justify-content-center">
                <input type="radio" name="encounter_class" class="btn-check" 
                        id="btn-check-4" value="<?=ConsultasConfiguracion::ENCOUNTER_CLASS_AMB?>">
                <label class="btn btn-soft-primary p-5" for="btn-check-4"><h3>Ambulatoria</h3></label>

                <input type="radio" name="encounter_class" class="btn-check" 
                        id="btn-check-5" value="<?=ConsultasConfiguracion::ENCOUNTER_CLASS_IMP?>">
                <label class="btn btn-soft-primary p-5" for="btn-check-5"><h3>Internacion</h3></label>

                <input type="radio" name="encounter_class" class="btn-check" 
                        id="btn-check-6" value="<?=ConsultasConfiguracion::ENCOUNTER_CLASS_EMER?>">
                <label class="btn btn-soft-primary p-5" for="btn-check-6"><h3>Guardia</h3></label>
            </div>
        </div>
    </div>
</div>