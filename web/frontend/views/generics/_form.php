<div class="row">
    <?php foreach($model->attributes() as $attribute) {
        var_dump($attribute);die;
        /*?>    
    <div class="col-md-4 col-sm-12 col-xs-12">
        <?= $form->field($model, $attribute->name)->dropDownList([ 'NO' => 'NO', 'SI' => 'SI', ], ['prompt' => '']) ?>
    </div>
    <?php*/ 
    $model->getTableSchema()->columns['attr']->type;
    } ?>
</div>