<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Emergency\Application\UseCase\IntakeEmergencyEpisode;

class EmergencyIntakeIdentityTest extends Unit
{
    public function testPareceIdentidadDniRequiereDocumentoYSexo(): void
    {
        $this->assertFalse(IntakeEmergencyEpisode::looksLikeDniIdentity([]));
        $this->assertFalse(IntakeEmergencyEpisode::looksLikeDniIdentity([
            'apellido' => 'Alonso',
            'nombre' => 'Ana',
            'documento' => '37123456',
            'fecha_nacimiento' => '1990-01-01',
        ]));
        $this->assertFalse(IntakeEmergencyEpisode::looksLikeDniIdentity([
            'documento' => '37123456',
        ]));
        $this->assertTrue(IntakeEmergencyEpisode::looksLikeDniIdentity([
            'documento' => '37.123.456',
            'sexo_biologico' => 1,
        ]));
        $this->assertTrue(IntakeEmergencyEpisode::looksLikeDniIdentity([
            'codigo_barras' => 'PDF417…',
        ]));
    }

    public function testPareceIdentidadDiditRequiereVerificationId(): void
    {
        $this->assertFalse(IntakeEmergencyEpisode::looksLikeDiditIdentity([]));
        $this->assertFalse(IntakeEmergencyEpisode::looksLikeDiditIdentity([
            'verification_id' => '   ',
        ]));
        $this->assertTrue(IntakeEmergencyEpisode::looksLikeDiditIdentity([
            'verification_id' => 'sess_abc',
        ]));
    }

    public function testPareceIdentidadPendiente(): void
    {
        $this->assertFalse(IntakeEmergencyEpisode::looksLikePendingIdentity([]));
        $this->assertTrue(IntakeEmergencyEpisode::looksLikePendingIdentity([
            'identidad_pendiente' => true,
        ]));
        $this->assertTrue(IntakeEmergencyEpisode::looksLikePendingIdentity([
            'identidad_pendiente' => '1',
        ]));
        $this->assertFalse(IntakeEmergencyEpisode::looksLikePendingIdentity([
            'identidad_pendiente' => '0',
        ]));
    }
}
