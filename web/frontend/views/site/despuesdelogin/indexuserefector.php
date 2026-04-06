<?php

?>
<div class="card">
    <div class="card-body">
        <div class="card-header">
            <h3>Seleccione el Efector en el cual desea trabajar para poder continuar</h3>
        </div>

        <div class="custom-table-effect">
            <div id="grid_efectores" class="d-flex flex-column gap-2"></div>
            <template id="tmpl_efector_radio">
                <div class="form-check">
                    <input type="radio" class="form-check-input" name="nombre_efector" />
                    <label class="form-check-label"></label>
                </div>
            </template>

        </div>
    </div>
</div>