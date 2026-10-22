<?php

namespace frontend\components\Clinical;

use Yii;

final class EncuestaParchesMamariosFormPageBuilder
{
    /**
     * @return array{idEfectorAutocomplete: int, idServicioAutocomplete: int, profesionalAutocompleteUrl: string}
     */
    public static function build(): array
    {
        return [
            'idEfectorAutocomplete' => (int) Yii::$app->user->getIdEfector(),
            'idServicioAutocomplete' => (int) Yii::$app->user->getServicioActual(),
            'profesionalAutocompleteUrl' => '/api/v1/profesional-efector-servicio/autocomplete',
        ];
    }
}
