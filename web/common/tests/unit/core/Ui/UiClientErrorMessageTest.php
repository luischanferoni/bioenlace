<?php

namespace common\tests\unit\core\Ui;

use Codeception\Test\Unit;
use common\components\Platform\Ui\UiClientErrorMessage;
use yii\base\UnknownPropertyException;
use yii\web\BadRequestHttpException;

class UiClientErrorMessageTest extends Unit
{
    public function testUnknownPropertyReturnsGenericMessage(): void
    {
        $msg = UiClientErrorMessage::fromThrowable(
            new UnknownPropertyException('Getting unknown property: common\\models\\Efector::idLocalidad')
        );

        $this->assertStringNotContainsString('idLocalidad', $msg);
        $this->assertStringContainsString('No pudimos completar', $msg);
    }

    public function testInvalidArgumentPassesThrough(): void
    {
        $msg = UiClientErrorMessage::fromThrowable(
            new \InvalidArgumentException('El horario ya no está disponible.')
        );

        $this->assertSame('El horario ya no está disponible.', $msg);
    }

    public function testHttpExceptionPassesThrough(): void
    {
        $msg = UiClientErrorMessage::fromThrowable(
            new BadRequestHttpException('Se requiere efector en sesión.')
        );

        $this->assertSame('Se requiere efector en sesión.', $msg);
    }
}
