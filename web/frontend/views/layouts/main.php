<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use frontend\assets\AppAsset;
use frontend\assets\BioenlaceApiClientAsset;
use frontend\components\Layout\MainLayoutPageBuilder;

AppAsset::register($this);

// Registrar CSS para el menú lateral responsive
$this->registerCssFile('@web/css/spa.css', ['depends' => [AppAsset::class]]);

// Registrar JavaScript para manejo de dropdowns del navbar
$this->registerJsFile('@web/js/navbar-dropdowns.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/bioenlace-fecha.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('@web/js/bioenlace-alertas.js', ['depends' => [BioenlaceApiClientAsset::class]]);

$layout = MainLayoutPageBuilder::build();

?>

<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Html::encode($layout['language']) ?>">

<head>
    <meta charset="<?= Html::encode($layout['charset']) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>

    <?php $this->head() ?>
</head>
<body class="boxed light theme-color-default">
    <?php $this->beginBody() ?>

    <?php if (!$layout['isGuest']): ?>
    <script>
    // Debe definirse ANTES de scripts POS_END (registerJs) para que AJAX incluya Bearer.
    window.userPerTabConfig = <?= \yii\helpers\Json::encode($layout['userPerTabConfig']) ?>;
    window.apiAuthToken = <?= json_encode($layout['apiAuthToken']); ?>;
    </script>
    <?php endif; ?>

    <script>
    // Helpers globales para consumir la API desde SPA web.
    window.spaConfig = {
        baseUrl: '<?= $layout['spaConfig']['baseUrl'] ?>',
        csrfToken: '<?= $layout['spaConfig']['csrfToken'] ?>',
        appVersion: <?= json_encode($layout['spaConfig']['appVersion'], JSON_UNESCAPED_UNICODE) ?>,
        asistenteUrl: <?= json_encode($layout['spaConfig']['asistenteUrl'], JSON_UNESCAPED_UNICODE) ?>
    };
    window.getBioenlaceApiClientHeaders = function (extra) {
        var ver = (window.spaConfig && window.spaConfig.appVersion) ? String(window.spaConfig.appVersion) : '1.0.0';
        var base = {
            'X-App-Client': 'web-frontend',
            'X-App-Version': ver,
            'X-Client': 'web'
        };
        if (window.apiAuthToken) {
            base['Authorization'] = 'Bearer ' + window.apiAuthToken;
        }
        return Object.assign(base, extra || {});
    };
    </script>

    <div class="boxed-inner">
        <main class="main-content">
            <nav class="nav navbar navbar-expand-xl navbar-light iq-navbar bg-white border-2 border-dark border-bottom">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <div class="d-flex align-items-center">
                            <img class="brand-logo" src="<?= Html::encode($layout['logoUrl']) ?>" alt="Bioenlace">
                        </div>
                        
                        <?php if (!$layout['isGuest']): ?>
                        <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($layout['listaEfectores']) && count($layout['listaEfectores']) > 1): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownEfector" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?= Html::encode($layout['efectorNombre']) ?>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownEfector">
                                        <?php foreach ($layout['listaEfectores'] as $idEfector => $nombreEfector): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="cambiarEfector(<?= $idEfector ?>); return false;">
                                                    <?= Html::encode($nombreEfector) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php elseif (!empty($layout['efectorNombre'])): ?>
                                <span class="text-muted small"><?= Html::encode($layout['efectorNombre']) ?></span>
                            <?php endif; ?>

                            <?php if (!$layout['hideServicioNavbar'] && !empty($layout['itemsMenuServicios']) && count($layout['itemsMenuServicios']) > 1): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownServicio" data-bs-toggle="dropdown" aria-expanded="false">
                                        Servicio: <?= Html::encode($layout['nombreServicio']) ?>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownServicio">
                                        <?php foreach ($layout['itemsMenuServicios'] as $item): ?>
                                            <li>
                                                <a class="dropdown-item <?= Html::encode($item['class']) ?>" href="<?= Html::encode($item['url']) ?>">
                                                    <?= Html::encode($item['label']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php elseif (!$layout['hideServicioNavbar'] && !empty($layout['nombreServicio']) && $layout['nombreServicio'] !== 'Seleccione un Servicio'): ?>
                                <span class="text-muted small">Servicio: <?= Html::encode($layout['nombreServicio']) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($layout['itemsMenuEncounters']) && count($layout['itemsMenuEncounters']) > 1): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownEncounter" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?= Html::encode($layout['encounterLabel']) ?>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownEncounter">
                                        <?php foreach ($layout['itemsMenuEncounters'] as $item): ?>
                                            <li>
                                                <a class="dropdown-item <?= Html::encode($item['class']) ?>" href="<?= Html::encode($item['url']) ?>">
                                                    <?= Html::encode($item['label']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php elseif ($layout['showEncounterLabel']): ?>
                                <span class="text-muted small">Tipo: <?= Html::encode($layout['encounterLabel']) ?></span>
                            <?php endif; ?>

                            <?= $this->render('_alertas_topmenu') ?>

                            <div class="dropdown">
                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?= Html::encode($layout['userName']) ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li><a class="dropdown-item" href="<?= Html::encode($layout['urls']['changePassword']) ?>">Modificar Contraseña</a></li>
                                    <li><a class="dropdown-item" href="<?= Html::encode($layout['urls']['confirmEmail']) ?>">Confirmación de email</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= Html::encode($layout['urls']['logout']) ?>">Salir</a></li>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <?= $this->render('_alertas_panel') ?>

            <div class="content-inner pb-0 d-flex" id="page_layout" style="min-height: calc(100vh - 60px); align-items: flex-start;">
                <!-- Menú vertical izquierdo -->
                <aside class="sidebar-menu bg-white rounded-4 border-2 border-dark border-end border-start border-top border-bottom m-4 mt-2 p-0 d-flex">
                    <nav class="navbar bg- flex-column w-100 justify-content-center">
                        <div class="container-fluid">
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link d-flex flex-column align-items-center justify-content-center <?= $layout['activeHome'] ? 'active' : '' ?>" href="<?= Html::encode($layout['urls']['home']) ?>">
                                        <i class="bi bi-people fs-2"></i> <?= Html::encode($layout['homeNavLabel']) ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link d-flex flex-column align-items-center justify-content-center <?= $layout['activeAssistant'] ? 'active' : '' ?>" href="<?= Html::encode($layout['urls']['assistant']) ?>">
                                        <i class="bi bi-stars fs-2"></i> Asistente
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </aside>
                
                <!-- Contenido principal -->
                <div class="container flex-grow-1 py-3 px-3">
                    <?= $content ?>
                </div>
            </div>
        </main>
    </div>
    <?php $this->endBody() ?>
    
    <?php if (!empty($layout['listaEfectores']) && count($layout['listaEfectores']) > 1): ?>
    <script>
    function cambiarEfector(idEfector) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= Html::encode($layout['urls']['setSession']) ?>';
        
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = '<?= Html::encode($layout['csrfToken']) ?>';
        form.appendChild(csrfInput);
        
        var efectorInput = document.createElement('input');
        efectorInput.type = 'hidden';
        efectorInput.name = 'idEfector';
        efectorInput.value = idEfector;
        form.appendChild(efectorInput);
        
        var encounterInput = document.createElement('input');
        encounterInput.type = 'hidden';
        encounterInput.name = 'encounterClass';
        encounterInput.value = '<?= Html::encode($layout['encounterClass']) ?>';
        form.appendChild(encounterInput);
        
        var servicioInput = document.createElement('input');
        servicioInput.type = 'hidden';
        servicioInput.name = 'servicio';
        servicioInput.value = '<?= Html::encode($layout['serviceActual']) ?>';
        form.appendChild(servicioInput);
        
        document.body.appendChild(form);
        form.submit();
    }
    </script>
    <?php endif; ?>
</body>
</html>
<?php $this->endPage() ?>
