<?php

use yii\helpers\Html;

use backend\assets\AppAsset;

use webvimark\modules\UserManagement\UserManagementModule;
use common\components\NavSisse;
use common\components\NavSisseHigh;

AppAsset::register($this);

$home = Yii::$app->getHomeUrl();
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
    <link href="<?= Yii::getAlias('@web/css/sidebar.css') ?>" rel="stylesheet">
</head>
<body class="">
    <?php $this->beginBody() ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
        <div class="position-sticky pt-3">
            <div class="d-flex align-items-center pb-3 mb-3 link-body-emphasis text-decoration-none border-bottom">
                <svg class="bi pe-none me-2" width="30" height="24" aria-hidden="true">
                    <use xlink:href="#bootstrap"></use>
                </svg>
                <span class="fs-5 fw-semibold">Bioenlace</span>
            </div>

            <?php
                echo NavSisse::widget([
                    'items' => [
                        [
                            'label' => 'Efectores',
                            'items' => [
                                ['label' => 'Listado', 'url' => ['/efectores/index']],
                                ['label' => 'Pisos', 'url' => ['/infraestructura-piso/index']],
                                ['label' => 'Salas', 'url' => ['/infraestructura-sala/index']],
                                ['label' => 'Camas', 'url' => ['/infraestructura-cama/index']],
                            ]
                        ],
                        [
                            'label' => 'Datos',
                            'items' => [
                                ['label' => 'Localidades', 'url' => ['/localidades/index']],
                                ['label' => 'Barrios', 'url' => ['/barrios/index']],
                                ['label' => 'Servicios', 'url' => ['/servicios/index']],
                                ['label' => 'Novedades', 'url' => ['/novedad/index']],
                                ['label' => 'Feriados', 'url' => ['/agenda-feriados/index']],
                                ['label' => 'Abreviaturas', 'url' => ['/abreviaturas/index']],
                            ]
                        ],
                        [
                            'label' => 'Abreviaturas',
                            'items' => [
                                ['label' => 'Listado', 'url' => ['/abreviaturas/index']],
                                ['label' => 'Estadísticas', 'url' => ['/abreviaturas/estadisticas']],
                                ['label' => 'Sugerencias Pendientes', 'url' => ['/abreviaturas/sugerencias-pendientes']],
                            ]
                        ],                        
                        [
                            'label' => 'Reportes',
                            'items' => [
                                ['label' => 'Reportes Estadísticos', 'url' => ['/personas/reportesestadisticos']],
                                ['label' => 'Reportes', 'url' => ['/personas/reporte']],
                                ['label' => 'Reportes Estadísticos Nivel Central', 'url' => ['/personas/reportesestadisticos-central']],
                                ['label' => 'Reportes Nivel Central', 'url' => ['/personas/reporte-central']],
                                ['label' => 'Atenciones de Enfermería', 'url' => ['/atenciones_enfermeria/reporte']],
                                ['label' => 'Reportes de Camas', 'url' => ['/infraestructura-cama/reportecamas']],
                            ]
                        ],
                        [
                            'label' => 'Personas',
                            'items' => [
                                ['label' => 'Listado de Personas', 'url' => ['/personas/index']],
                            ]
                        ],
                        [
                            'label' => 'Administrar',
                            'items' => UserManagementModule::menuItems()
                        ],
                        [
                            'label' => 'Forms',
                            'items' => [
                                ['label' => 'Crear', 'url' => ['/form/create']],
                                ['label' => 'Listado', 'url' => ['/form/forms']],
                            ]
                        ],
                        [
                            'label' => 'Consultas',
                            'url' => ['/consultas-configuracion/index'],
                        ],
                    ],
                ]);
            ?>

            <div class="sidebar-footer mt-auto">
                <?php
                echo NavSisseHigh::widget([
                    'options' => ['class' => 'navbar-nav'],
                    'encodeLabels' => false,
                    'activateParents' => true,

                    'items' => !Yii::$app->user->isGuest ? [

                        [
                            'label' => '<span><h6><i class="bi bi-person-circle"></i> ' . Yii::$app->user->identity->username . '</h6></span>',
                            'linkOptions' => ['class' => 'dropdown-item border-end'],
                            'items' => [
                                ['label' => 'Modificar Contraseña', 'url' => ['/user-management/auth/change-own-password']],
                                ['label' => 'Confirmacion de email', 'url' => ['/user-management/auth/confirm-email']],
                            ],
                        ],
                        [
                            'label' => '<span><h6>SALIR <i class="bi bi-box-arrow-right"></i></h6></span>',
                            'url' => ['/user-management/auth/logout'],
                        ]

                    ] : [
                        [
                            'label' => '<h6><i class="bi bi-box-arrow-up"></i> INGRESAR</h6>',
                            'url' => ['/user-management/auth/login']

                        ]
                    ]
                ]); ?>

                <?php if (getenv("SISSE_SHOW_TEST_ENVIRONMENT_WARNING") == "Y"): ?>
                <div class="alert alert-warning" role="alert">
                AMBIENTE DE PRUEBAS   
                </div>
                <?php endif;?>
            </div>
        </div>
    </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <button class="btn btn-outline-secondary me-2 d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                        <span data-feather="menu" class="align-text-bottom"></span>
                        Menu
                    </button>
                    <h1 class="h2"><?= $this->title ?></h1>
                </div>

                <?= $content ?>
            </main>
        </div>
    </div>
    <footer class="footer">
            <div class="footer-body">
                <ul class="left-panel list-inline mb-0 p-0">
                    <p>© SISSE <?=date("Y")?> - MINISTERIO DE SALUD DE SANTIAGO DEL ESTERO</p>
                </ul>
                <div class="right-panel">
                    <img src="<?= Yii::getAlias('@web') ?>/images/logoSD.png" width="100px" alt="">
                </div>
            </div>
        </footer>   
    <?php $this->endBody() ?>
</body>


</html>
<?php $this->endPage() ?>