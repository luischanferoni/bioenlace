<?php

/**
 * @var \common\models\Platform\LoginForm|null $model
 * @var bool $passwordLoginEnabled
 * @var bool $enableAutoLogin
 */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

$showPasswordForm = isset($model) && ($passwordLoginEnabled ?? true);
?>

<div class="container-fluid">
    <section class="login-content overflow-hidden">
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
                    <div class="col-md-9">
                        <div class="card d-flex justify-content-center mb-0 auth-card iq-auth-form">
                            <div class="card-body text-center">
                                <?php if ($showPasswordForm): ?>
                                    <?php $form = ActiveForm::begin([
                                        'id' => 'login-form',
                                        'options' => ['autocomplete' => 'off', 'class' => 'text-start'],
                                        'validateOnBlur' => false,
                                        'fieldConfig' => ['template' => "{input}\n{error}"],
                                    ]) ?>

                                    <?= $form->field($model, 'username')
                                        ->textInput(['placeholder' => $model->getAttributeLabel('username'), 'autocomplete' => 'off']) ?>

                                    <?= $form->field($model, 'password')
                                        ->passwordInput(['placeholder' => $model->getAttributeLabel('password'), 'autocomplete' => 'off']) ?>

                                    <?= ($enableAutoLogin ?? false) ? $form->field($model, 'rememberMe')->checkbox(['value' => true]) : '' ?>

                                    <div class="d-flex justify-content-center">
                                        <?= Html::submitButton('Ingresar', ['class' => 'btn btn-outline-primary mb-4']) ?>
                                    </div>

                                    <?php ActiveForm::end() ?>
                                <?php endif; ?>

                                <div class="registration-block">
                                    <?= Html::a('¿No podés ingresar? Recuperar acceso', ['/auth/recovery-request']) ?>
                                </div>
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
