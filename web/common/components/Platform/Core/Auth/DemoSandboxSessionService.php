<?php

namespace common\components\Platform\Core\Auth;

use common\components\Domain\Organization\Service\Seed\DemoSandboxClinicalSeedService;
use common\components\Domain\Organization\Service\Seed\DemoSandboxPurgeService;
use common\components\Domain\Organization\Service\Seed\DemoSandboxStaffProvisionService;
use common\components\Domain\Organization\Service\Seed\EfectorDemoSeedService;
use common\models\Efector;
use common\models\Platform\DemoSandboxAccess;
use common\models\Platform\DemoSandboxSession;
use common\models\User;
use Yii;

/**
 * Orquesta sesión demo efímera: provision → seed → tracking → purga.
 *
 * @see web/docs/plans/demo-sandbox-institucional/design.md
 */
final class DemoSandboxSessionService
{
    public const Yii_SESSION_KEY = 'demo_sandbox_session_id';

    /**
     * Crea médico temporal + seed clínico y registra la sesión.
     *
     * @return array{user: User, session: DemoSandboxSession}
     */
    public function provisionEphemeralStaff(?int $idAccess = null): array
    {
        $cfg = $this->config();
        $idEfector = $this->resolveIdEfector($cfg);
        $servicioNombre = trim((string) ($cfg['servicio_nombre'] ?? 'MED GENERAL'));
        if ($servicioNombre === '') {
            $servicioNombre = 'MED GENERAL';
        }
        $seedCfg = is_array($cfg['seed'] ?? null) ? $cfg['seed'] : [];
        $withAgenda = (bool) ($seedCfg['with_agenda'] ?? true);
        $sessionTtl = max(600, (int) ($cfg['session_ttl_seconds'] ?? 14400));

        $staff = (new DemoSandboxStaffProvisionService())->provision(
            $idEfector,
            $servicioNombre,
            $withAgenda
        );

        $clinical = (new DemoSandboxClinicalSeedService())->seedForStaff(
            $staff['id_efector'],
            $staff['id_pes'],
            $staff['id_servicio'],
            $staff['id_user'],
            [
                'pacientes' => (int) ($seedCfg['pacientes'] ?? 4),
                'turnos' => (int) ($seedCfg['turnos'] ?? 2),
                'with_consulta_amb' => (bool) ($seedCfg['with_consulta_amb'] ?? true),
                'with_guardia' => (bool) ($seedCfg['with_guardia'] ?? true),
                'with_internacion' => (bool) ($seedCfg['with_internacion'] ?? true),
            ]
        );

        $user = User::findOne($staff['id_user']);
        if ($user === null) {
            throw new \RuntimeException('Usuario demo recién creado no encontrado.');
        }

        $now = date('Y-m-d H:i:s');
        $session = new DemoSandboxSession();
        $session->id_access = $idAccess;
        $session->role = DemoSandboxAccess::ROLE_STAFF;
        $session->id_efector = $staff['id_efector'];
        $session->id_user = $staff['id_user'];
        $session->id_persona = $staff['id_persona'];
        $session->id_pes = $staff['id_pes'];
        $session->id_servicio = $staff['id_servicio'];
        $session->username = $staff['username'];
        $session->expires_at = date('Y-m-d H:i:s', time() + $sessionTtl);
        $session->created_at = $now;
        $session->setSeedPayload([
            'marker' => DemoSandboxStaffProvisionService::SEED_MARKER,
            'documento_staff' => $staff['documento'],
            'id_agenda' => $staff['id_agenda'],
            'paciente_ids' => $clinical['paciente_ids'],
            'turno_ids' => $clinical['turno_ids'],
            'encounter_ids' => $clinical['encounter_ids'],
            'guardia_ids' => $clinical['guardia_ids'],
            'internacion_ids' => $clinical['internacion_ids'],
            'cama_ids' => $clinical['cama_ids'],
            'sala_ids' => $clinical['sala_ids'],
            'piso_ids' => $clinical['piso_ids'],
            'documentos_pacientes' => $clinical['documentos_pacientes'],
        ]);
        if (!$session->save(false)) {
            throw new \RuntimeException('No se pudo persistir demo_sandbox_session.');
        }

        return [
            'user' => $user,
            'session' => $session,
        ];
    }

    public function bindToYiiSession(DemoSandboxSession $session): void
    {
        if (Yii::$app->has('session', true)) {
            Yii::$app->session->set(self::Yii_SESSION_KEY, (int) $session->id);
        }
    }

    /**
     * Purga la sesión demo de la request actual (si hay).
     *
     * @return array{purged: bool, already: bool, errors: list<string>}|null
     */
    public function purgeCurrentYiiSession(): ?array
    {
        if (!Yii::$app->has('session', true)) {
            return null;
        }
        $id = (int) Yii::$app->session->get(self::Yii_SESSION_KEY, 0);
        Yii::$app->session->remove(self::Yii_SESSION_KEY);
        if ($id <= 0) {
            return null;
        }

        /** @var DemoSandboxSession|null $row */
        $row = DemoSandboxSession::findOne($id);
        if ($row === null) {
            return null;
        }

        return (new DemoSandboxPurgeService())->purgeSession($row);
    }

    /**
     * @return array{scanned: int, purged: int, errors: list<string>}
     */
    public function purgeExpired(): array
    {
        return (new DemoSandboxPurgeService())->purgeExpired();
    }

    /**
     * Resuelve el efector plantilla: override numérico opcional, o codigo_sisa DEV (nunca un centro real por defecto).
     *
     * @param array<string, mixed> $cfg
     */
    private function resolveIdEfector(array $cfg): int
    {
        $override = (int) ($cfg['id_efector'] ?? 0);
        if ($override > 0) {
            if ($override === EfectorDemoSeedService::DEFAULT_EFECTOR_REF) {
                throw new \DomainException(
                    'demo_sandbox.id_efector=' . $override
                    . ' es un efector real. Usá efector_codigo_sisa=DEV99002PRIV (id_efector=0).'
                );
            }
            if (Efector::findOne($override) === null) {
                throw new \DomainException(
                    "demo_sandbox.id_efector={$override} no existe. Usá un efector DEV o corré clinical-seed/efector-demo-contexto."
                );
            }

            return $override;
        }

        $codigo = trim((string) ($cfg['efector_codigo_sisa'] ?? EfectorDemoSeedService::COD_SISA_PRIVATE));
        if ($codigo === '') {
            $codigo = EfectorDemoSeedService::COD_SISA_PRIVATE;
        }

        $seed = new EfectorDemoSeedService();
        $row = $seed->findByCodigoSisa($codigo);
        if ($row === null) {
            // Auto-crear plantilla DEV (sin médico permanente) para no caer en efectores reales.
            if ($codigo === EfectorDemoSeedService::COD_SISA_PRIVATE) {
                $created = $seed->upsertClinicaPrivada();
                $row = ['id_efector' => (int) ($created['id_efector'] ?? 0)];
            } elseif ($codigo === EfectorDemoSeedService::COD_SISA_PUBLIC_OTRA_PROV) {
                $created = $seed->upsertPublicOtraProvincia();
                $row = ['id_efector' => (int) ($created['id_efector'] ?? 0)];
            } else {
                throw new \DomainException(
                    'No hay efector demo plantilla (' . $codigo . '). Ejecutá: php yii clinical-seed/efector-demo-contexto'
                );
            }
        }

        $id = (int) ($row['id_efector'] ?? 0);
        if ($id <= 0) {
            throw new \DomainException('No se pudo resolver id_efector de la plantilla demo.');
        }
        if ($id === EfectorDemoSeedService::DEFAULT_EFECTOR_REF) {
            throw new \DomainException(
                'La plantilla demo no puede ser el efector real '
                . EfectorDemoSeedService::DEFAULT_EFECTOR_REF
                . '. Usá codigo_sisa DEV (DEV99002PRIV).'
            );
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $cfg = Yii::$app->params['demo_sandbox'] ?? [];

        return is_array($cfg) ? $cfg : [];
    }
}
