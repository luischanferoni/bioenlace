<?php

?>
<div class="card">
    <div class="card-body">
        <div class="card-header">
            <h3>Seleccione el Efector en el cual desea trabajar para poder continuar</h3>
        </div>

        <div class="custom-table-effect">
            <div id="grid_efectores" class="d-flex flex-column gap-2"></div>

            <div id="sesion-operativa-estado-vacio" class="d-none mt-3" role="status" aria-live="polite">
                <div class="alert alert-warning mb-0">
                    <h4 class="alert-heading h5">No hay efectores listos para operar</h4>
                    <p id="sesion-operativa-estado-vacio-lead" class="mb-2 small text-body-secondary"></p>
                    <div id="sesion-operativa-estado-vacio-body"></div>
                </div>
            </div>

            <template id="tmpl_efector_radio">
                <div class="form-check">
                    <input type="radio" class="form-check-input" name="nombre_efector" />
                    <label class="form-check-label"></label>
                </div>
            </template>

        </div>
    </div>
</div>