<?php

namespace frontend\components;

use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\ActionEvent;

/**
 * Shell web staff: solo autenticación. RBAC de negocio en API v1.
 */
class EnforceGhostAccessBootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        $app->on(Application::EVENT_BEFORE_ACTION, function (ActionEvent $event) {
            $action = $event->action;
            $controller = $action->controller;

            // Only enforce for classic web frontend controllers (not API modules).
            $class = get_class($controller);
            if (strpos($class, 'frontend\\controllers\\') !== 0) {
                return;
            }

            $filter = new FrontendAuthenticatedAccessControl();
            $filter->attach($controller);

            if (!$filter->beforeAction($action)) {
                $event->isValid = false;
            }
        });
    }
}

