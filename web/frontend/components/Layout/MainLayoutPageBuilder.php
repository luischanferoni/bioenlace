<?php

namespace frontend\components\Layout;

use common\components\Platform\Core\Product\ClientContextMetadata;
use common\models\Clinical\EncounterDefinition;
use Yii;
use yii\helpers\Url;

final class MainLayoutPageBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $app = Yii::$app;
        $user = $app->user;
        $spaLayout = SpaLayoutPageBuilder::build();
        $isGuest = $spaLayout['isGuest'];
        $encounterClass = $user->getEncounterClass();
        $listaEncounters = EncounterDefinition::sessionSelectableClasses();
        $labelsEncounter = EncounterDefinition::ENCOUNTER_CLASS;
        $itemsMenuEncounters = [];
        foreach ($listaEncounters as $key => $value) {
            $itemsMenuEncounters[] = [
                'label' => $value,
                'url' => Url::to(['site/cambiar-encounter-class', 'codigo' => $key]),
                'class' => 'alerta-cambio-encounter',
            ];
        }

        $listaServicios = $user->getServicios();
        $itemsMenuServicios = [];
        $nombreServicio = 'Seleccione un Servicio';
        $servicioActual = $user->getServicioActual();
        if (!is_null($listaServicios)) {
            foreach ($listaServicios as $key => $value) {
                $itemsMenuServicios[] = [
                    'label' => $value,
                    'url' => Url::to(['site/cambiar-servicio', 'id_servicio' => $key]),
                    'class' => 'alerta-cambio-servicio',
                ];

                if (count($listaServicios) === 1 || $servicioActual == $key) {
                    $nombreServicio = $value;
                }
            }
        }

        $efectorNombre = $user->getNombreEfector() ?: '';
        $encounterLabels = $labelsEncounter[$encounterClass]
            ?? $listaEncounters[$encounterClass]
            ?? 'N/A';
        $controller = $app->controller;
        $actionId = $controller ? $controller->action->id : null;

        return [
            'isGuest' => $isGuest,
            'language' => $spaLayout['language'],
            'charset' => $spaLayout['charset'],
            'listaEfectores' => $user->getEfectores(),
            'encounterClass' => $encounterClass,
            'itemsMenuEncounters' => $itemsMenuEncounters,
            'encounterLabel' => $encounterLabels,
            'showEncounterLabel' => !empty($encounterClass) && isset($labelsEncounter[$encounterClass]),
            'itemsMenuServicios' => $itemsMenuServicios,
            'nombreServicio' => $nombreServicio,
            'efectorNombre' => $efectorNombre,
            'userName' => $user->username ?: '',
            'hideServicioNavbar' => !$isGuest
                && ClientContextMetadata::shouldHideServicioInNavbar((int) $user->id),
            'logoUrl' => Yii::getAlias('@web') . '/images/logo.svg',
            'userPerTabConfig' => $spaLayout['userPerTabConfig'],
            'apiAuthToken' => $spaLayout['apiAuthToken'],
            'spaConfig' => $spaLayout['spaConfig'] + [
                'asistenteUrl' => Url::to(['/site/asistente']),
            ],
            'urls' => [
                'changePassword' => Url::to(['/auth/change-own-password']),
                'confirmEmail' => Url::to(['/auth/confirm-email']),
                'logout' => Url::to(['/auth/logout']),
                'home' => Url::to(['/site/index']),
                'assistant' => Url::to(['/site/asistente']),
                'setSession' => Url::to(['site/establecer-sesion-final']),
            ],
            'activeHome' => $controller && $controller->id === 'site' && $actionId === 'index',
            'activeAssistant' => $actionId === 'asistente',
            'csrfToken' => $spaLayout['spaConfig']['csrfToken'],
            'serviceActual' => $servicioActual,
        ];
    }
}
