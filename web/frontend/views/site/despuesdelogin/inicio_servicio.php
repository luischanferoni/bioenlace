<?php ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pb-0">
        <h2 class="h5 mb-0">Para finalizar, seleccione el servicio</h2>
    </div>
    <div class="card-body">
        <div id="div_servicios" class="d-flex flex-column flex-md-row flex-wrap gap-3 py-2 justify-content-center"></div>
        <template id="tmpl_servicio">
            <div>
                <input type="radio" name="servicio" class="btn-check" />
                <label class="btn btn-soft-primary px-4 py-4 w-100"><h3 class="h5 mb-0"></h3></label>
            </div>
        </template>
    </div>
</div>
