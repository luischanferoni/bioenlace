<?php

namespace common\components\Platform\Core\Db;

use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\console\Application as ConsoleApplication;
use yii\web\Application as WebApplication;

/**
 * Ping/reconexión al inicio de cada request web o comando de consola.
 */
final class EnsureDbConnectionBootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        if ($app instanceof WebApplication) {
            $app->on(Application::EVENT_BEFORE_REQUEST, static function (): void {
                BioenlaceDb::ensureAllConnections();
            });

            return;
        }
        if ($app instanceof ConsoleApplication) {
            $app->on(Application::EVENT_BEFORE_REQUEST, static function (): void {
                BioenlaceDb::ensureAllConnections();
            });
        }
    }
}
