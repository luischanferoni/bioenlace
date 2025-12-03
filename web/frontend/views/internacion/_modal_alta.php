<?php
use yii\bootstrap5\Modal;
use yii\helpers\Html;
use yii\helpers\Url;
?>
<?php
Modal::begin([
    'title' => 'Alta Internación',
    'id' => 'modal_internacion_alta',
    'size' => 'modal-lg',
  ]);
?>
<div id="modal_internacion_alta_body">
</div>
<?php
Modal::end();

$this->registerJsVar("modal_alta_url", Url::to(['internacion/update']));
$js = <<<EOJS
var modal_alta = document.getElementById('modal_internacion_alta');
var modal_alta_body = $('#modal_internacion_alta_body');

modal_alta.addEventListener('show.bs.modal', function (event) {
    $.get(
        modal_alta_url,
        {id: '$model->id'},
        function(data) {
            modal_alta_body.html(data);
        }
    );
});

$(document).on('click', '.modal-alta-show-link', function(e) {
    e.preventDefault();
    var modal = bootstrap.Modal.getInstance(modal_alta);
    modal.show();
});

$(document).on('click', '#mdl_alta_btn_submit', function(e) {
    e.preventDefault();
    var form = $('#frm_internacion_alta');
    var form_data = {};
    $.each(form.serializeArray(), function(i, field) {
        form_data[field.name] = field.value;
    });
    $.post(
        form.attr("action"),
        form_data,
        function(data) {
            modal_alta_body.html(data);
            var modal = bootstrap.Modal.getInstance(modal_alta);
            modal.handleUpdate()
        }
    );
});
EOJS;

$this->registerJs($js);