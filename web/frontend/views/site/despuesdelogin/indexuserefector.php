<?php

?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pb-0">
        <h2 class="h5 mb-0">Seleccione el efector en el cual desea trabajar</h2>
    </div>
    <div class="card-body">
        <div id="grid_efectores" class="d-flex flex-column gap-2"></div>

        <div id="sesion-operativa-estado-vacio" class="d-none mt-3" role="status" aria-live="polite">
            <div class="alert alert-warning mb-0">
                <h3 class="alert-heading h6">No hay efectores listos para operar</h3>
                <p id="sesion-operativa-estado-vacio-lead" class="mb-2 small text-body-secondary"></p>
                <div id="sesion-operativa-estado-vacio-body"></div>
            </div>
        </div>

        <template id="tmpl_efector_radio">
            <div class="form-check border rounded-3 p-3 bg-body">
                <input type="radio" class="form-check-input" name="nombre_efector" />
                <label class="form-check-label"></label>
            </div>
        </template>
    </div>
</div>
