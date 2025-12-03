<div class="col-lg-3 group__item" draggable="false">
    <div class="card">
        <div class="card-body">
            <div class="d-grid grid-flow-col align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center">
                    <span class="badge bg-soft-info mb-3">Cama N°: <?= $nroCama ?></span>
                </div>
                <div class="dropdown">
                    <span class="h5" id="dropDown-011" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <svg width="22" viewBox="0 0 22 5" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M19.6788 5C20.9595 5 22 3.96222 22 2.68866C22 1.41318 20.9595 0.373465 19.6788 0.373465C18.3981 0.373465 17.3576 1.41318 17.3576 2.68866C17.3576 3.96222 18.3981 5 19.6788 5ZM11.0005 5C12.2812 5 13.3217 3.96222 13.3217 2.68866C13.3217 1.41318 12.2812 0.373465 11.0005 0.373465C9.71976 0.373465 8.67929 1.41318 8.67929 2.68866C8.67929 3.96222 9.71976 5 11.0005 5ZM4.64239 2.68866C4.64239 3.96222 3.60192 5 2.3212 5C1.04047 5 0 3.96222 0 2.68866C0 1.41318 1.04047 0.373465 2.3212 0.373465C3.60192 0.373465 4.64239 1.41318 4.64239 2.68866Z" fill="currentColor"></path>
                        </svg>
                    </span>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropDown-011" style="">
                        <?= $operaciones; ?>
                    </div>
                </div>
            </div>
            <h6 class="mb-2"><?= $nombre?></h6>
            <small class="">DNI: <?= $documento?></small>            
        </div>
        <span class="remove"></span>
    </div>
</div>
