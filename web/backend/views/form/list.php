<div class="container-fluid iq-container">
  <div class="row">
    <div class="col-md-12">
      <div class="flex-wrap d-flex justify-content-between align-items-center">
        <div>
          <h1>Formularios disponibles</h1>
          <p>Carga los formularios que tengas pendientes.</p>
        </div>
        <div>
          <a href="create" class="btn btn-info">          
            Agregar Nuevo
          </a>
        </div>
      </div>
    </div>
  </div>

            <?php if (Yii::$app->session->hasFlash('error')): ?>
              <div class="row">
                <div class="card ">        
                  <div class="card-body">
                    <div id="message" class="bg-soft-warning pt-5 text-center pb-5  rounded font-size-24">                   
                    <?= Yii::$app->session->getFlash('error');?>
                    </div>
                    </div>
                </div>
              </div>
            <?php endif; ?>
            <?php if (Yii::$app->session->hasFlash('success')): ?>
              <div class="row">
                <div class="card ">        
                  <div class="card-body">
                    <div id="message" class="bg-soft-success text-center pt-5 pb-5  rounded font-size-24">                   
                        <?= Yii::$app->session->getFlash('success');?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

  <div class="row row-cols-1 row-cols-md-2 mb-2 text-center">          
        <?php

if (count($data) > 0) {
    foreach ($data as $item) {
        // Se controla si el fomulario es uno por persona ya sea perfil efector o paciente
        if ($item['cantidad'] > 0 && ($item["formTipoId"] == 1 || $item["formTipoId"] == 3)) {
            $enlace = '<a href="instancias/' . $item['id'] . '" class="btn btn-success ">
              <span class="badge badge-light">' . $item['cantidad'] . '</span> Instancias
              </a>
              <a href="#" class="btn btn-default pull-right">El formulario ya fue cargado</a>
              ';
        } else {
            $enlace = '<div class="d-grid gap-2 d-md-flex justify-content-md-center">
              <a href="instancias/' . $item['id'] . '" class="btn btn-success ">
                <span class="badge badge-light">' . $item['cantidad'] . '</span> Instancias
              </a>
              <a href="render/' . $item['id'] . '" class="btn btn-primary ">
                Cargar Datos
              </a>
              </div>';
        }
        /*echo '<div class="col-sm-6 mb-3 mb-sm-0">
                    <div class="card text-dark bg-light">
                      <div class="card-header">' . $item['nombre'] . '</div>
                      <div class="card-body">
                        <p class="card-text">' . $item['descripcion'] . '</p>
                        ' . $enlace . '
                      </div>
                    </div>
                    </div>';*/
        echo '  <div class="col">
                  <div class="card mb-4">
                    <div class="card-header bg-soft-primary p-4">
                      <h4 class="card-title pricing-card-title mb-3"><b>' . $item['nombre'] . '</b></h4>
                      <svg fill="none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M8.92574 16.39H14.3119C14.7178 16.39 15.0545 16.05 15.0545 15.64C15.0545 15.23 14.7178 14.9 14.3119 14.9H8.92574C8.5198 14.9 8.18317 15.23 8.18317 15.64C8.18317 16.05 8.5198 16.39 8.92574 16.39ZM12.2723 9.9H8.92574C8.5198 9.9 8.18317 10.24 8.18317 10.65C8.18317 11.06 8.5198 11.39 8.92574 11.39H12.2723C12.6782 11.39 13.0149 11.06 13.0149 10.65C13.0149 10.24 12.6782 9.9 12.2723 9.9ZM19.3381 9.02561C19.5708 9.02292 19.8242 9.02 20.0545 9.02C20.302 9.02 20.5 9.22 20.5 9.47V17.51C20.5 19.99 18.5099 22 16.0545 22H8.17327C5.59901 22 3.5 19.89 3.5 17.29V6.51C3.5 4.03 5.5 2 7.96535 2H13.2525C13.5099 2 13.7079 2.21 13.7079 2.46V5.68C13.7079 7.51 15.203 9.01 17.0149 9.02C17.4381 9.02 17.8112 9.02316 18.1377 9.02593C18.3917 9.02809 18.6175 9.03 18.8168 9.03C18.9578 9.03 19.1405 9.02789 19.3381 9.02561ZM19.6111 7.566C18.7972 7.569 17.8378 7.566 17.1477 7.559C16.0527 7.559 15.1507 6.648 15.1507 5.542V2.906C15.1507 2.475 15.6685 2.261 15.9646 2.572C16.5004 3.13476 17.2368 3.90834 17.9699 4.67837C18.7009 5.44632 19.4286 6.21074 19.9507 6.759C20.2398 7.062 20.0279 7.565 19.6111 7.566Z" fill="currentColor" />
                      </svg>
                    </div>
                    <div class="card-body">
                      <h5 class="mb-5">' . $item['descripcion'] . '</h5>
                      ' . $enlace . '
                    </div>
                  </div>
                </div>';
    }
} else {
    echo '<div class="alert alert-info" role="alert">No se encontraron formularios.</div>';
}
?>
</div>
</div>
