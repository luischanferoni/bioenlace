<?php

namespace frontend\components;

use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\ActionEvent;

/**
 * Enforces Webvimark GhostAccessControl for ALL web frontend controllers.
 *
 * This avoids relying on each controller's behaviors() to include ghost-access.
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

            $filter = new SisseGhostAccessControl();
            $filter->attach($controller);

            if (!$filter->beforeAction($action)) {
                // Filter already handled denyAccess/redirect.
                $event->isValid = false;
            }
        });
    }
}

