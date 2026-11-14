<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\RowContract\EnfermeriaSignosVitalesRowContract;
use common\models\Person\Persona;
use common\models\Person\PersonaRepository;
use Yii;

/**
 * Curvas de crecimiento: leen `atenciones_enfermeria.datos` por clave JSON.
 *
 * Regresión: las claves legacy con SNOMED (`162879003p`) empiezan con dígito y en
 * MySQL 8 requieren comillas en el path (`$."162879003p"`); sin ellas la consulta
 * falla entera y la curva no se dibuja.
 */
class CrecimientoDatosTest extends Unit
{
    public function testGrowthCurvesReadKeysWrittenByTheCapture(): void
    {
        $persona = $this->personaConDatos([
            'peso' => '18.5',
            'talla' => '110',
            'perimetro_cefalico' => '50',
        ]);

        $datos = PersonaRepository::getDatosCrecimiento($persona);

        $this->assertNotNull($datos);
        $this->assertSame([18.5], $this->column($datos['peso'] ?? null));
        $this->assertSame([110.0], $this->column($datos['talla'] ?? null));
        $this->assertSame([50.0], $this->column($datos['perimetro_cefalico'] ?? null));
    }

    public function testGrowthCurvesStillReadLegacySnomedKeys(): void
    {
        $persona = $this->personaConDatos([
            '162879003p' => '12',
            '162879003t' => '90',
            '363812007' => '46',
        ]);

        $datos = PersonaRepository::getDatosCrecimiento($persona);

        $this->assertNotNull($datos);
        $this->assertSame([12.0], $this->column($datos['peso'] ?? null));
        $this->assertSame([90.0], $this->column($datos['talla'] ?? null));
        $this->assertSame([46.0], $this->column($datos['perimetro_cefalico'] ?? null));
    }

    public function testContractKeysAreTheOnesTheCurvesQuery(): void
    {
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            'Peso' => '18,5',
            'Talla' => '110',
            'Perimetro cefalico' => '50',
        ]);

        $persona = $this->personaConDatos($datos);
        $crecimiento = PersonaRepository::getDatosCrecimiento($persona);

        $this->assertSame([18.5], $this->column($crecimiento['peso'] ?? null));
        $this->assertSame([110.0], $this->column($crecimiento['talla'] ?? null));
        $this->assertSame([50.0], $this->column($crecimiento['perimetro_cefalico'] ?? null));
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function personaConDatos(array $datos): Persona
    {
        $personaId = (int) (new \yii\db\Query())
            ->select('id_persona')
            ->from('{{%personas}}')
            ->scalar(Yii::$app->db);

        $encounterId = (int) (new \yii\db\Query())
            ->select('id')
            ->from('{{%encounter}}')
            ->scalar(Yii::$app->db);

        $efectorId = (int) (new \yii\db\Query())
            ->select('id_efector')
            ->from('{{%efectores}}')
            ->scalar(Yii::$app->db);

        Yii::$app->db->createCommand()->insert('{{%atenciones_enfermeria}}', [
            'encounter_id' => $encounterId,
            'id_persona' => $personaId,
            'id_user' => 1,
            'id_efector' => $efectorId,
            'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'fecha_creacion' => date('Y-m-d H:i:s'),
            'hora_creacion' => date('H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ])->execute();

        $persona = Persona::findOne($personaId);
        $this->assertNotNull($persona);
        $persona->fecha_nacimiento = date('Y-m-d', strtotime('-5 years'));

        return $persona;
    }

    /**
     * @param mixed $value JSON devuelto por la consulta
     * @return list<float>
     */
    private function column($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v): float => (float) $v, $value));
    }
}
