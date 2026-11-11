<?php

/**
 * Alta de cuenta desde la web. La vista sólo pinta pasos; toda la lógica de request
 * vive en `frontend/controllers/AltaController` y en `frontend/web/js/alta-vinculo.js`.
 *
 * @var array $page view model armado por el controlador
 */

use yii\helpers\Html;

$urls = $page['urls'] ?? [];
?>

<div class="container-fluid">
    <section
        class="login-content"
        id="alta-root"
        data-iniciar-url="<?= Html::encode($urls['iniciar'] ?? '') ?>"
        data-estado-url="<?= Html::encode($urls['estado'] ?? '') ?>"
        data-cancelar-url="<?= Html::encode($urls['cancelar'] ?? '') ?>"
        data-centros-url="<?= Html::encode($urls['centros'] ?? '') ?>"
        data-provincias-url="<?= Html::encode($urls['provincias'] ?? '') ?>"
        data-poll-seconds="<?= (int) ($page['poll_seconds'] ?? 2) ?>"
    >
        <div class="row no-gutters align-items-center bg-white">
            <div class="col-md-12 col-lg-6 align-self-center">
                <img
                    src="<?= Yii::getAlias('@web') ?>/images/logo.svg"
                    alt="Bioenlace"
                    class="login-brand-logo mb-4 mt-5"
                    width="240"
                    height="51"
                >
                <div class="row justify-content-center pt-3">
                    <div class="col-md-10">
                        <div class="card d-flex justify-content-center mb-0 auth-card iq-auth-form">
                            <div class="card-body">

                                <?php if (empty($page['enabled'])): ?>
                                    <h1 class="h3 mb-3">Alta no disponible</h1>
                                    <p class="text-muted mb-4">
                                        El alta de cuentas desde la web no está habilitada en este entorno.
                                        Podés crear tu cuenta desde la app <strong>Personal de Salud</strong>.
                                    </p>
                                    <?= Html::a('Ir al ingreso', ['/auth/login'], ['class' => 'btn btn-outline-primary']) ?>
                                <?php else: ?>

                                    <div data-panel="form">
                                        <h1 class="h3 mb-3">Creá tu cuenta</h1>
                                        <p class="text-muted">
                                            Elegí el centro de salud al que pertenecés. Después vas a
                                            verificar tu identidad con el teléfono.
                                        </p>

                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <label class="form-label" for="alta-sector">Sector</label>
                                                <select class="form-select" id="alta-sector" data-field="sector">
                                                    <option value="">Todos</option>
                                                    <option value="PUBLICO">Público</option>
                                                    <option value="PRIVADO">Privado</option>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label" for="alta-provincia">Provincia</label>
                                                <select class="form-select" id="alta-provincia" data-field="id_provincia">
                                                    <option value="">Todas</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="alta-busqueda">Buscar centro</label>
                                            <input
                                                type="search"
                                                class="form-control"
                                                id="alta-busqueda"
                                                data-field="q"
                                                placeholder="Nombre del centro"
                                                autocomplete="off"
                                            >
                                        </div>

                                        <div
                                            class="list-group mb-3"
                                            data-results
                                            role="listbox"
                                            aria-label="Centros disponibles"
                                        >
                                            <p class="text-muted small mb-0" data-results-hint>
                                                Buscá tu centro por nombre o filtrá por sector y provincia.
                                            </p>
                                        </div>

                                        <div class="alert alert-danger d-none" role="alert" data-form-error></div>

                                        <template data-template="center-result">
                                            <button
                                                type="button"
                                                class="list-group-item list-group-item-action"
                                                role="option"
                                                aria-selected="false"
                                                data-center
                                            >
                                                <span class="fw-semibold" data-center-name></span>
                                                <span class="d-block small text-muted" data-center-meta></span>
                                            </button>
                                        </template>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <?= Html::a('¿Ya tenés cuenta? Ingresá', ['/auth/login'], ['class' => 'small']) ?>
                                            <button type="button" class="btn btn-primary" data-action="start" disabled>
                                                Continuar
                                            </button>
                                        </div>
                                    </div>

                                    <div data-panel="qr" class="d-none text-center">
                                        <h2 class="h4 mb-3">Escaneá el código</h2>
                                        <p class="text-muted">
                                            Abrí la cámara de tu teléfono y apuntá al código QR.
                                            Se va a abrir una página en tu teléfono.
                                        </p>
                                        <div class="d-flex justify-content-center my-3" data-qr></div>
                                        <p class="text-muted small" data-qr-center></p>
                                        <p class="text-danger" role="status" aria-live="polite" data-qr-status></p>
                                        <button type="button" class="btn btn-outline-secondary" data-action="cancel">
                                            Cancelar
                                        </button>
                                    </div>

                                    <div data-panel="code" class="d-none text-center">
                                        <h2 class="h4 mb-3">Escribí este código en tu teléfono</h2>
                                        <p class="display-5 fw-bold mb-3" data-code aria-live="polite"></p>
                                        <p class="text-muted">
                                            En la página que se abrió en tu teléfono ingresá este código.
                                            Vence en pocos minutos.
                                        </p>
                                        <p class="text-danger" role="status" aria-live="polite" data-code-status></p>
                                        <button type="button" class="btn btn-outline-secondary" data-action="cancel">
                                            Cancelar
                                        </button>
                                    </div>

                                    <div data-panel="waiting" class="d-none text-center">
                                        <h2 class="h4 mb-3">Verificá tu identidad en el teléfono</h2>
                                        <div class="spinner-border text-primary my-3" role="status">
                                            <span class="visually-hidden">Esperando la verificación</span>
                                        </div>
                                        <p class="text-muted">
                                            Cuando termines en el teléfono, esta pantalla va a continuar sola.
                                        </p>
                                        <button type="button" class="btn btn-outline-secondary" data-action="cancel">
                                            Cancelar
                                        </button>
                                    </div>

                                    <div data-panel="ready" class="d-none text-center">
                                        <h2 class="h4 mb-3">Identidad verificada</h2>
                                        <p class="text-muted">
                                            Ya verificamos tu identidad. Estamos terminando de crear tu cuenta.
                                        </p>
                                    </div>

                                    <div data-panel="expired" class="d-none text-center">
                                        <h2 class="h4 mb-3">El código venció</h2>
                                        <p class="text-muted">No pasó nada: podés volver a empezar.</p>
                                        <button type="button" class="btn btn-primary" data-action="restart">
                                            Volver a intentar
                                        </button>
                                    </div>

                                    <div data-panel="error" class="d-none text-center">
                                        <h2 class="h4 mb-3">No pudimos continuar</h2>
                                        <p class="text-danger" role="alert" data-error-message></p>
                                        <button type="button" class="btn btn-primary" data-action="restart">
                                            Volver a intentar
                                        </button>
                                    </div>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 pe-0">
                <div class="login-side-pattern" aria-hidden="true"></div>
            </div>
        </div>
    </section>
</div>
