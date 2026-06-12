<?php
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 pb-0">
        <h2 class="h5 mb-0">Seleccione el área en donde trabajará</h2>
    </div>
    <div class="card-body">
        <div id="encounter_classes_container" class="d-flex flex-column flex-md-row flex-wrap gap-3 py-2 justify-content-center"></div>
        <template id="tmpl_encounter_class">
            <div>
                <input type="radio" name="encounter_class" class="btn-check" />
                <label class="btn btn-soft-primary px-4 py-4 w-100"><h3 class="h5 mb-0"></h3></label>
            </div>
        </template>
    </div>
</div>
