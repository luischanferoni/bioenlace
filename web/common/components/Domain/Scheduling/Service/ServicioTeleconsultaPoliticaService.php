<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Organization\Service\Authorization\EfectorAccessService;
use common\components\Domain\Organization\Service\SesionOperativa\SesionOperativaService;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\models\ReservaTriageTeleconsultaElegibilidad;
use common\models\Servicio;
use common\models\ServicioTeleconsultaCaso;
use common\models\ServiciosEfector;
use common\models\User;
use Yii;
use yii\db\Transaction;

/**
 * Lectura y persistencia de teleconsulta_politica (+ allowlist) para servicios del efector (AdminEfector).
 */
final class ServicioTeleconsultaPoliticaService
{
    /**
     * @return array<string, mixed>
     */
    public function listarEnEfector(int $idEfector): array
    {
        $this->assertAdminEfectorOperativo();
        $this->assertServicioEnEfector(0, $idEfector, true);

        $items = [];
        foreach ($this->serviciosEnEfector($idEfector) as $row) {
            $idServicio = (int) ($row['id_servicio'] ?? 0);
            $politica = $this->normalizarPolitica((string) ($row['teleconsulta_politica'] ?? ''));
            $items[] = [
                'id_servicio' => $idServicio,
                'nombre' => (string) ($row['nombre'] ?? ''),
                'teleconsulta_politica' => $politica,
                'casos_count' => $politica === Servicio::TELECONSULTA_POLITICA_ALGUNAS
                    ? count(ServicioTeleconsultaCaso::listCodigosPorServicio($idServicio))
                    : 0,
            ];
        }

        return [
            'servicios' => $items,
            'resumen_efector' => $this->resumenEfector($idEfector),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function guardar(int $idEfector, array $input): array
    {
        $this->assertAdminEfectorOperativo();

        $idServicio = (int) ($input['id_servicio'] ?? 0);
        if ($idServicio <= 0) {
            throw new \InvalidArgumentException('Elegí un servicio.');
        }
        $this->assertServicioEnEfector($idServicio, $idEfector);

        $politica = $this->normalizarPolitica((string) ($input['teleconsulta_politica'] ?? ''));
        if (!in_array($politica, Servicio::teleconsultaPoliticaValues(), true)) {
            throw new \InvalidArgumentException('Política de teleconsulta inválida.');
        }

        $casos = $this->parseCasoCodigos($input);
        if ($politica === Servicio::TELECONSULTA_POLITICA_ALGUNAS && $casos === []) {
            throw new \InvalidArgumentException(
                'Indicá al menos un código de triage para la política «Algunos motivos».'
            );
        }

        $servicio = Servicio::findOne($idServicio);
        if ($servicio === null) {
            throw new \InvalidArgumentException('Servicio no encontrado.');
        }

        $tx = Yii::$app->db->beginTransaction(Transaction::READ_COMMITTED);
        try {
            $servicio->teleconsulta_politica = $politica;
            if (!$servicio->save(false, ['teleconsulta_politica'])) {
                throw new \RuntimeException('No se pudo guardar la política.');
            }

            ServicioTeleconsultaCaso::deleteAll(['id_servicio' => $idServicio]);
            if ($politica === Servicio::TELECONSULTA_POLITICA_ALGUNAS) {
                foreach ($casos as $code) {
                    $row = new ServicioTeleconsultaCaso();
                    $row->id_servicio = $idServicio;
                    $row->caso_codigo = $code;
                    if (!$row->save(false)) {
                        throw new \RuntimeException('No se pudo guardar un código de triage.');
                    }
                }
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'success' => true,
            'message' => 'Política de teleconsulta actualizada.',
            'data' => [
                'id_servicio' => $idServicio,
                'teleconsulta_politica' => $politica,
                'caso_codigos' => $politica === Servicio::TELECONSULTA_POLITICA_ALGUNAS ? $casos : [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resumenEfector(int $idEfector): array
    {
        $insight = (new StaffModalidadInsightMetricsService())->resumen([
            'id_efector' => $idEfector,
            'fecha_hasta' => date('Y-m-d'),
        ]);

        $conVideo = 0;
        foreach ($this->serviciosEnEfector($idEfector) as $row) {
            $pol = $this->normalizarPolitica((string) ($row['teleconsulta_politica'] ?? ''));
            if ($pol !== Servicio::TELECONSULTA_POLITICA_NINGUNA) {
                $conVideo++;
            }
        }

        return [
            'presencial_insight_sugerido' => (int) ($insight['presencial_insight_sugerido'] ?? 0),
            'presencial_con_triage' => (int) ($insight['presencial_con_triage'] ?? 0),
            'pct_sugerido' => $insight['pct_sugerido'],
            'servicios_con_teleconsulta' => $conVideo,
            'servicios_total' => count($this->serviciosEnEfector($idEfector)),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function opcionesServiciosUi(int $idEfector): array
    {
        $out = [];
        foreach ($this->serviciosEnEfector($idEfector) as $row) {
            $id = (int) ($row['id_servicio'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'value' => (string) $id,
                'label' => (string) ($row['nombre'] ?? ('Servicio ' . $id)),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function opcionesCasosTriageUi(): array
    {
        $out = [];
        $rows = ReservaTriageTeleconsultaElegibilidad::find()
            ->orderBy(['prioridad' => SORT_DESC, 'triage_codigo' => SORT_ASC])
            ->all();
        foreach ($rows as $row) {
            $code = trim((string) $row->triage_codigo);
            if ($code === '') {
                continue;
            }
            $eleg = trim((string) $row->elegibilidad);
            $label = $code . ($eleg !== '' ? ' (' . $eleg . ')' : '');
            $out[] = ['value' => $code, 'label' => $label];
        }

        return $out;
    }

    public static function usuarioEsAdminEfectorOperativo(): bool
    {
        if (User::hasRole(['AdminEfector'], false)) {
            return true;
        }
        $idServicio = (int) Yii::$app->user->getServicioActual();

        return $idServicio > 0 && SesionOperativaService::isServicioAdminEfector($idServicio);
    }

    private function assertAdminEfectorOperativo(): void
    {
        if (!self::usuarioEsAdminEfectorOperativo()) {
            throw new DomainOperationForbiddenException(
                'Solo AdminEfector puede configurar la política de teleconsulta del efector.'
            );
        }
    }

    private function assertServicioEnEfector(int $idServicio, int $idEfector, bool $soloEfector = false): void
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('Se requiere efector en sesión.');
        }
        if ($soloEfector) {
            return;
        }
        $exists = ServiciosEfector::find()
            ->where(['id_efector' => $idEfector, 'id_servicio' => $idServicio])
            ->andWhere(['deleted_at' => null])
            ->exists();
        if (!$exists) {
            throw new \InvalidArgumentException('El servicio no pertenece a este efector.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviciosEnEfector(int $idEfector): array
    {
        return ServiciosEfector::find()
            ->alias('se')
            ->innerJoin(['s' => Servicio::tableName()], 's.id_servicio = se.id_servicio')
            ->where(['se.id_efector' => $idEfector])
            ->andWhere(['se.deleted_at' => null])
            ->andWhere(['s.acepta_turnos' => 'SI'])
            ->select(['s.id_servicio', 's.nombre', 's.teleconsulta_politica'])
            ->orderBy(['s.nombre' => SORT_ASC])
            ->asArray()
            ->all();
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function parseCasoCodigos(array $input): array
    {
        if (isset($input['caso_codigos']) && is_array($input['caso_codigos'])) {
            $codes = [];
            foreach ($input['caso_codigos'] as $c) {
                $v = trim((string) $c);
                if ($v !== '') {
                    $codes[$v] = $v;
                }
            }

            return array_values($codes);
        }

        $text = trim((string) ($input['caso_codigos_text'] ?? $input['caso_codigos'] ?? ''));
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/[\s,;]+/u', $text) ?: [];
        $codes = [];
        foreach ($parts as $p) {
            $v = trim((string) $p);
            if ($v !== '') {
                $codes[$v] = $v;
            }
        }

        return array_values($codes);
    }

    private function normalizarPolitica(string $politica): string
    {
        $p = strtoupper(trim($politica));

        return $p !== '' ? $p : Servicio::TELECONSULTA_POLITICA_NINGUNA;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function resolveIdEfector(array $params): int
    {
        return EfectorAccessService::assertAndResolveIdEfector(
            'servicio-teleconsulta.configurar-efector-flow',
            $params
        );
    }
}
