<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use frontend\assets\AppAsset;
use yii\helpers\Url;
use common\models\ConsultasConfiguracion;
use common\models\Consulta;

AppAsset::register($this);

// Registrar CSS para el menú lateral responsive
$this->registerCssFile('@web/css/spa.css', ['depends' => [\yii\web\JqueryAsset::class]]);

// Registrar JavaScript para manejo de dropdowns del navbar
$this->registerJsFile('@web/js/navbar-dropdowns.js', ['depends' => [\yii\web\JqueryAsset::class]]);

$home = Yii::$app->getHomeUrl();
$apellidoUsuario = Yii::$app->user->getApellidoUsuario();
$nombreUsuario = Yii::$app->user->getNombreUsuario();

$listaEfectores = Yii::$app->user->getEfectores();

$encounterClass = Yii::$app->user->getEncounterClass();
$listaEncounters = ConsultasConfiguracion::ENCOUNTER_CLASS;
$itemsMenuEncounters = [];
if (!is_null($listaEncounters)) {
    foreach ($listaEncounters as $key => $value) {
        $itemsMenuEncounters[] = ['label' => $value, 'url' => ['site/cambiar-encounter-class', 'codigo' => $key], 'linkOptions' => ['class' => 'alerta-cambio-encounter']];
    }
}

$listaServicios = Yii::$app->user->getServicios();
$itemsMenuServicios = [];
$nombreServicio = "Seleccione un Servicio";
if (!is_null($listaServicios)) {
    foreach ($listaServicios as $key => $value) {
        $itemsMenuServicios[] = ['label' => $value, 'url' => ['site/cambiar-servicio', 'id_servicio' => $key], 'linkOptions' => ['class' => 'alerta-cambio-servicio']];

        if(count($listaServicios) == 1 || Yii::$app->user->getServicioActual() == $key) {        
            $nombreServicio = $value;
        }
    }
}

if (Yii::$app->user->getNombreEfector()) {
    $efector_nombre = Yii::$app->user->getNombreEfector();
} else {
    $efector_nombre = '';
}
if (Yii::$app->user->username) {
    $user = Yii::$app->user->username;
} else {
    $user = '';
}

?>

<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">

<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>

    <?php $this->head() ?>
</head>
<body class="boxed light theme-color-default">
    <?php $this->beginBody() ?>

    <div class="boxed-inner">
        <main class="main-content">
            <nav class="nav navbar navbar-expand-xl navbar-light iq-navbar bg-white border-2 border-dark border-bottom">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <div class="d-flex align-items-center">
                            <img class="img-fluid" src="<?php echo Yii::getAlias('@web').'/'?>images/logo_small.png">
                        </div>
                        
                        <?php if (!Yii::$app->user->isGuest): ?>
                        <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($listaEfectores) && count($listaEfectores) > 1): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownEfector" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?= Html::encode($efector_nombre) ?>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownEfector">
                                        <?php foreach ($listaEfectores as $idEfector => $nombreEfector): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="cambiarEfector(<?= $idEfector ?>); return false;">
                                                    <?= Html::encode($nombreEfector) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php elseif (!empty($efector_nombre)): ?>
                                <span class="text-muted small"><?= Html::encode($efector_nombre) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($itemsMenuServicios) && count($itemsMenuServicios) > 1): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownServicio" data-bs-toggle="dropdown" aria-expanded="false">
                                        Servicio: <?= Html::encode($nombreServicio) ?>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownServicio">
                                        <?php foreach ($itemsMenuServicios as $item): ?>
                                            <li>
                                                <a class="dropdown-item <?= $item['linkOptions']['class'] ?? '' ?>" href="<?= Url::to($item['url']) ?>">
                                                    <?= Html::encode($item['label']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php elseif (!empty($nombreServicio) && $nombreServicio !== "Seleccione un Servicio"): ?>
                                <span class="text-muted small">Servicio: <?= Html::encode($nombreServicio) ?></span>
                            <?php endif; ?>

                            <?php if (!empty($itemsMenuEncounters) && count($itemsMenuEncounters) > 1): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownEncounter" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?= Html::encode($listaEncounters[$encounterClass] ?? 'N/A') ?>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownEncounter">
                                        <?php foreach ($itemsMenuEncounters as $item): ?>
                                            <li>
                                                <a class="dropdown-item <?= $item['linkOptions']['class'] ?? '' ?>" href="<?= Url::to($item['url']) ?>">
                                                    <?= Html::encode($item['label']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php elseif (!empty($encounterClass) && isset($listaEncounters[$encounterClass])): ?>
                                <span class="text-muted small">Tipo: <?= Html::encode($listaEncounters[$encounterClass]) ?></span>
                            <?php endif; ?>

                            <div class="dropdown">
                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?= Html::encode($user) ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li><a class="dropdown-item" href="<?= Url::to(['/user-management/auth/change-own-password']) ?>">Modificar Contraseña</a></li>
                                    <li><a class="dropdown-item" href="<?= Url::to(['/user-management/auth/confirm-email']) ?>">Confirmación de email</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= Url::to(['/user-management/auth/logout']) ?>">Salir</a></li>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <div class="content-inner pb-0 d-flex" id="page_layout" style="min-height: calc(100vh - 60px); align-items: flex-start;">
                <!-- Menú vertical izquierdo -->
                <aside class="sidebar-menu bg-white rounded-4 border-1 border-dark border-end border-start border-top border-bottom m-4 mt-2 p-0 d-flex">
                    <nav class="navbar bg- flex-column w-100 justify-content-center">
                        <div class="container-fluid">
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link d-flex flex-column align-items-center justify-content-center <?= (Yii::$app->controller->action->id == 'index' || Yii::$app->controller->action->id == 'inicio') ? 'active' : '' ?>" href="<?= Url::to(['/site/index']) ?>">
                                        <i class="bi bi-house-door fs-2"></i> Inicio
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link d-flex flex-column align-items-center justify-content-center <?= Yii::$app->controller->action->id == 'acciones' ? 'active' : '' ?>" href="<?= Url::to(['/site/acciones']) ?>">
                                        <i class="bi bi-lightning-charge fs-2"></i> Acciones
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </aside>
                
                <!-- Contenido principal -->
                <div class="container flex-grow-1 py-5 px-5">
                    <?= $content ?>
                </div>
            </div>
        </main>
    </div>
    <?php $this->endBody() ?>
    
    <?php if (!Yii::$app->user->isGuest): ?>
    <script>
    // Inicializar userPerTabConfig para que esté disponible en todas las peticiones AJAX
    window.userPerTabConfig = <?= \yii\helpers\Json::encode(Yii::$app->user->getPerTabSessions()) ?>;
    </script>
    <?php endif; ?>
    
    <script>
    // Inicializar variables globales para la SPA
    window.spaConfig = {
        baseUrl: '<?= rtrim(Yii::$app->urlManager->createAbsoluteUrl(['/']), '/') ?>',
        csrfToken: '<?= Yii::$app->request->csrfToken ?>'
    };
    
    // Calcular y establecer la altura del navbar para el posicionamiento del sidebar
    function updateNavbarHeight() {
        const navbar = document.querySelector('.iq-navbar');
        if (navbar) {
            const height = navbar.offsetHeight;
            document.documentElement.style.setProperty('--navbar-height', height + 'px');
        }
    }
    
    // Ejecutar al cargar y al redimensionar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateNavbarHeight);
    } else {
        updateNavbarHeight();
    }
    window.addEventListener('resize', updateNavbarHeight);
    </script>
    
    <?php if (!empty($listaEfectores) && count($listaEfectores) > 1): ?>
    <script>
    function cambiarEfector(idEfector) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= Url::to(['site/establecer-session-final']) ?>';
        
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf';
        csrfInput.value = '<?= Yii::$app->request->csrfToken ?>';
        form.appendChild(csrfInput);
        
        var efectorInput = document.createElement('input');
        efectorInput.type = 'hidden';
        efectorInput.name = 'idEfector';
        efectorInput.value = idEfector;
        form.appendChild(efectorInput);
        
        var encounterInput = document.createElement('input');
        encounterInput.type = 'hidden';
        encounterInput.name = 'encounterClass';
        encounterInput.value = '<?= $encounterClass ?>';
        form.appendChild(encounterInput);
        
        var servicioInput = document.createElement('input');
        servicioInput.type = 'hidden';
        servicioInput.name = 'servicio';
        servicioInput.value = '<?= Yii::$app->user->getServicioActual() ?>';
        form.appendChild(servicioInput);
        
        document.body.appendChild(form);
        form.submit();
    }
    </script>
    <?php endif; ?>
</body>
</html>
<?php $this->endPage() ?>
