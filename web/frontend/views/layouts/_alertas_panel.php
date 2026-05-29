<?php
/** Panel lateral de alertas (fuera del navbar; position fixed). */
?>
<div
    id="spa-alertas-panel"
    class="spa-alertas-panel"
    aria-hidden="true"
    role="dialog"
    aria-label="Alertas"
>
    <div class="spa-alertas-panel-inner shadow">
        <div class="spa-alertas-panel-header d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <span class="fw-semibold">Alertas</span>
            <button type="button" id="spa-alertas-close-btn" class="btn btn-sm btn-link">Cerrar</button>
        </div>
        <div class="spa-alertas-panel-body overflow-auto"></div>
    </div>
</div>
