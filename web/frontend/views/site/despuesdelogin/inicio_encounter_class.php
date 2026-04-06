<?php
?>
<div class="card">
    <div class="card-body">
        <div class="card-header">
            <h3>Seleccione el Área en donde trabajará</h3>
        </div>

        <div>
            <div id="encounter_classes_container" class="d-flex flex-column flex-md-row p-4 gap-4 py-md-5 align-items-center justify-content-center"></div>
            <template id="tmpl_encounter_class">
                <div>
                    <input type="radio" name="encounter_class" class="btn-check" />
                    <label class="btn btn-soft-primary p-5"><h3></h3></label>
                </div>
            </template>
        </div>
    </div>
</div>