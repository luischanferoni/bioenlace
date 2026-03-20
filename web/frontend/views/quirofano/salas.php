<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var common\models\busquedas\QuirofanoSalaBusqueda $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int $idEfector */

$this->title = 'Salas de quirófano';
$this->params['breadcrumbs'][] = ['label' => 'Agenda quirúrgica', 'url' => ['index', 'id_efector' => $idEfector]];
$this->params['breadcrumbs'][] = $this->title;

$base = rtrim(Url::to(['/'], true), '/');
$apiDeleteBase = $base . '/api/v1/quirofano/salas/';
?>
<div class="quirofano-salas">
    <h1><?= Html::encode($this->title) ?></h1>

    <div id="qf-msg" class="alert d-none" role="alert"></div>

    <p>
        <?= Html::a('Nueva sala', ['create-sala', 'id_efector' => $idEfector], ['class' => 'btn btn-success']) ?>
        <?= Html::a('Cirugías', ['index', 'id_efector' => $idEfector], ['class' => 'btn btn-default']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            'id',
            'nombre',
            'codigo',
            [
                'attribute' => 'activo',
                'value' => function ($m) {
                    return $m->activo ? 'Sí' : 'No';
                },
                'filter' => [1 => 'Sí', 0 => 'No'],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{update} {delete}',
                'buttons' => [
                    'delete' => function ($url, $model) use ($idEfector) {
                        return Html::a('Eliminar', '#', [
                            'class' => 'btn btn-link text-danger qf-delete-sala',
                            'data-id' => (int) $model->id,
                            'data-confirm' => '¿Eliminar esta sala?',
                        ]);
                    },
                    'update' => function ($url, $model) use ($idEfector) {
                        return Html::a('Editar', ['update-sala', 'id' => $model->id, 'id_efector' => $idEfector], [
                            'class' => 'btn btn-link',
                        ]);
                    },
                ],
            ],
        ],
    ]); ?>
</div>

<?php
$apiDeleteBaseJson = json_encode($apiDeleteBase);
$js = <<<JS
(function () {
    var apiBase = {$apiDeleteBaseJson};

    function msg(text, kind) {
        var el = document.getElementById('qf-msg');
        if (!el) return;
        el.className = 'alert ' + (kind || 'alert-info');
        el.textContent = text;
        el.classList.remove('d-none');
    }

    function headers() {
        var h = { 'Accept': 'application/json' };
        if (typeof window.getBioenlaceApiClientHeaders === 'function') {
            h = Object.assign({}, window.getBioenlaceApiClientHeaders(h));
        }
        if (window.apiAuthToken) {
            h['Authorization'] = 'Bearer ' + window.apiAuthToken;
        }
        return h;
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target.closest('.qf-delete-sala');
        if (!t) return;
        ev.preventDefault();
        if (t.getAttribute('data-confirm') && !window.confirm(t.getAttribute('data-confirm'))) {
            return;
        }
        var sid = t.getAttribute('data-id');
        if (!sid) return;
        fetch(apiBase + sid, { method: 'DELETE', credentials: 'same-origin', headers: headers() })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
                if (!x.ok || !x.j || !x.j.success) {
                    msg((x.j && x.j.message) ? x.j.message : 'No se pudo eliminar.', 'alert-danger');
                    return;
                }
                window.location.reload();
            })
            .catch(function () { msg('Error de red.', 'alert-danger'); });
    });
})();
JS;
$this->registerJs($js, \yii\web\View::POS_READY);
