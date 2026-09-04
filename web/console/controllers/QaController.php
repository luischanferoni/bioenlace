<?php

namespace console\controllers;

use common\components\Platform\Assistant\Qa\AsistenteConsultasQaService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Smoke de consultas al asistente (paciente) con IA real.
 *
 * @see web/docs/qa/paciente/asistente-consultas.md
 * @see web/common/data/qa/asistente-consultas.yaml
 */
class QaController extends Controller
{
    /** @var int Usuario paciente (permisos de intents paciente). */
    public $userId = 0;

    /** @var string Coberturas separadas por coma (default: Hoy). Vacío = todas. */
    public $cobertura = 'Hoy';

    /** @var string Filtrar por seccion del catálogo (ej. smoke). */
    public $seccion = '';

    /** @var string Ejecutar un solo case id. */
    public $id = '';

    /** @var int Máximo de casos (0 = sin límite). */
    public $limit = 0;

    /** @var bool Solo listar casos filtrados, sin llamar al asistente. */
    public $list = false;

    /** @var string Ruta absoluta o @alias del reporte JSON (opcional). */
    public $report = '';

    /**
     * @param string $actionID
     * @return list<string>
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'userId',
            'cobertura',
            'seccion',
            'id',
            'limit',
            'list',
            'report',
        ]);
    }

    /**
     * @param string $actionID
     * @return list<string>
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'u' => 'userId',
            'c' => 'cobertura',
            's' => 'seccion',
            'l' => 'limit',
            'r' => 'report',
        ]);
    }

    /**
     * Ejecuta el catálogo de consultas paciente (ChatOrchestrator, IA real).
     *
     * Ejemplos:
     *   php yii qa/asistente-consultas --userId=123
     *   php yii qa/asistente-consultas --userId=123 --cobertura=Hoy,Fuera --seccion=smoke
     *   php yii qa/asistente-consultas --userId=123 --id=smoke-sintoma-cabeza
     *   php yii qa/asistente-consultas --list=1 --cobertura=Hoy
     */
    public function actionAsistenteConsultas(): int
    {
        $coberturas = $this->parseCoberturas($this->cobertura);
        $cases = AsistenteConsultasQaService::filterCases(
            $coberturas,
            trim($this->seccion) !== '' ? trim($this->seccion) : null,
            trim($this->id) !== '' ? trim($this->id) : null,
            $this->limit > 0 ? (int) $this->limit : null
        );

        if ($cases === []) {
            $this->stderr("No hay casos con el filtro indicado.\n", Console::FG_YELLOW);

            return ExitCode::OK;
        }

        if ($this->list) {
            $this->stdout(sprintf("Casos (%d):\n", count($cases)), Console::BOLD);
            foreach ($cases as $case) {
                $this->stdout(sprintf(
                    "  %-36s  %-8s  %-16s  %s\n",
                    $case['id'],
                    $case['cobertura'],
                    $case['seccion'],
                    $case['tipo']
                ));
            }

            return ExitCode::OK;
        }

        if ((int) $this->userId <= 0) {
            $this->stderr("Indique --userId=<id> de un usuario paciente.\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $this->ensureQaLogTarget();

        $reportPath = trim($this->report);
        if ($reportPath !== '' && str_starts_with($reportPath, '@')) {
            $reportPath = Yii::getAlias($reportPath);
        }
        if ($reportPath === '') {
            $reportPath = null;
        }

        $this->stdout(sprintf(
            "Ejecutando %d caso(s) con userId=%d (IA real)…\n",
            count($cases),
            (int) $this->userId
        ), Console::BOLD);

        try {
            $batch = AsistenteConsultasQaService::run($cases, (int) $this->userId, $reportPath);
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($batch['results'] as $result) {
            $this->imprimirResultado($result);
        }

        $summary = is_array($batch['summary'] ?? null) ? $batch['summary'] : [];
        $this->stdout("\n=== Resumen ===\n", Console::BOLD);
        $this->stdout(sprintf(
            "total=%d pass=%d fail=%d observe=%d error=%d\n",
            (int) ($summary['total'] ?? 0),
            (int) ($summary['pass'] ?? 0),
            (int) ($summary['fail'] ?? 0),
            (int) ($summary['observe'] ?? 0),
            (int) ($summary['error'] ?? 0)
        ));
        $this->stdout('Reporte: ' . (string) ($batch['report_path'] ?? '') . "\n");

        if ((int) ($summary['fail'] ?? 0) > 0 || (int) ($summary['error'] ?? 0) > 0) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function imprimirResultado(array $result): void
    {
        $status = (string) ($result['status'] ?? '?');
        if ($status === 'pass') {
            $color = Console::FG_GREEN;
        } elseif ($status === 'fail' || $status === 'error') {
            $color = Console::FG_RED;
        } elseif ($status === 'observe') {
            $color = Console::FG_YELLOW;
        } else {
            $color = Console::FG_GREY;
        }

        $this->stdout(sprintf(
            "[%s] %s (%s / %s)\n",
            strtoupper($status),
            (string) ($result['id'] ?? ''),
            (string) ($result['cobertura'] ?? ''),
            (string) ($result['seccion'] ?? '')
        ), $color);

        $last = is_array($result['last'] ?? null) ? $result['last'] : null;
        if ($last !== null) {
            $this->stdout(sprintf(
                "  goal=%s kind=%s intents=%s\n",
                (string) ($last['user_goal'] ?? ''),
                (string) ($last['kind'] ?? ''),
                json_encode($last['intent_refs'] ?? [], JSON_UNESCAPED_UNICODE)
            ));
            $reply = (string) ($last['reply_text'] ?? '');
            if ($reply !== '') {
                $preview = mb_strlen($reply) > 160 ? mb_substr($reply, 0, 157) . '…' : $reply;
                $this->stdout('  reply: ' . $preview . "\n");
            }
        }

        $failures = is_array($result['failures'] ?? null) ? $result['failures'] : [];
        foreach ($failures as $f) {
            $this->stdout('  ! ' . $f . "\n", Console::FG_RED);
        }
    }

    /**
     * @return list<string>|null null = sin filtro
     */
    private function parseCoberturas(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '*' || strcasecmp($raw, 'all') === 0) {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $parts === [] ? null : $parts;
    }

    private function ensureQaLogTarget(): void
    {
        if (!Yii::$app->has('log')) {
            return;
        }
        $logger = Yii::$app->getLog();
        $path = Yii::getAlias('@runtime/logs/qa-asistente-consultas.log');
        $logger->targets['qa-asistente-consultas'] = Yii::createObject([
            'class' => \yii\log\FileTarget::class,
            'categories' => ['qa-asistente-consultas', 'asistente-planning'],
            'logFile' => $path,
            'logVars' => [],
            'levels' => ['info', 'warning', 'error'],
            'exportInterval' => 1,
        ]);
    }
}
