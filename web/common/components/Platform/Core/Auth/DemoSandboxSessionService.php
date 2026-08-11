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
     * Crea staff temporal + seed clínico y registra la sesión.
     *
     * @param 'medico'|'enfermeria'|'administrativo' $staffKind
     * @return array{user: User, session: DemoSandboxSession}
     */
    public function provisionEphemeralStaff(?int $idAccess = null, string $staffKind = 'medico'): array
    {
        $cfg = $this->config();
        $idEfector = $this->resolveIdEfector($cfg);
        $spec = $this->staffProvisionSpec($staffKind, $cfg);
        $sessionTtl = (int) $spec['cobertura_ttl_seconds'];

        $staff = (new DemoSandboxStaffProvisionService())->provision(
            $idEfector,
            (string) $spec['servicio_nombre'],
            (bool) $spec['with_agenda'],
            [
                'rbac_role' => (string) $spec['rbac_role'],
                'apellido' => (string) $spec['apellido'],
                'username_prefix' => (string) $spec['username_prefix'],
                'cobertura_classes' => $spec['cobertura_classes'],
                'cobertura_ttl_seconds' => $sessionTtl,
            ]
        );

        $clinical = (new DemoSandboxClinicalSeedService())->seedForStaff(
            $staff['id_efector'],
            $staff['id_pes'],
            $staff['id_servicio'],
            $staff['id_user'],
            [
                'pacientes' => (int) $spec['pacientes'],
                'turnos' => (int) $spec['turnos'],
                'with_consulta_amb' => (bool) $spec['with_consulta_amb'],
                'with_consulta_async' => (bool) $spec['with_consulta_async'],
                'consultas_async' => (int) $spec['consultas_async'],
                'with_guardia' => (bool) $spec['with_guardia'],
                'with_internacion' => (bool) $spec['with_internacion'],
            ]
        );

        $user = User::findOne($staff['id_user']);
        if ($user === null) {
            throw new \RuntimeException('Usuario demo recién creado no encontrado.');
        }

        $now = date('Y-m-d H:i:s');
        $session = new DemoSandboxSession();
        $session->id_access = $idAccess;
        $session->role = (string) $spec['role'];
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
            'async_encounter_ids' => $clinical['async_encounter_ids'] ?? [],
            'guardia_ids' => $clinical['guardia_ids'],
            'internacion_ids' => $clinical['internacion_ids'],
            'cama_ids' => $clinical['cama_ids'],
            'sala_ids' => $clinical['sala_ids'],
            'piso_ids' => $clinical['piso_ids'],
            'documentos_pacientes' => $clinical['documentos_pacientes'],
            'fecha_turnos' => $clinical['fecha_turnos'] ?? null,
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
     * Resuelve el efector plantilla solo por codigo_sisa DEV.
     * Un id_efector numérico en params-local (p. ej. 863) se ignora si no es plantilla DEV.
     *
     * @param array<string, mixed> $cfg
     */
    private function resolveIdEfector(array $cfg): int
    {
        $codigo = trim((string) ($cfg['efector_codigo_sisa'] ?? EfectorDemoSeedService::COD_SISA_PRIVATE));
        if ($codigo === '') {
            $codigo = EfectorDemoSeedService::COD_SISA_PRIVATE;
        }
        if (!str_starts_with($codigo, 'DEV')) {
            throw new \DomainException(
                'demo_sandbox.efector_codigo_sisa debe ser un código DEV (p. ej. DEV99002PRIV), recibido: ' . $codigo
            );
        }

        $override = (int) ($cfg['id_efector'] ?? 0);
        if ($override > 0) {
            $efOverride = Efector::findOne($override);
            $codOverride = $efOverride !== null ? trim((string) $efOverride->codigo_sisa) : '';
            // Solo aceptar override si YA es plantilla DEV con el mismo codigo_sisa.
            if (
                $efOverride !== null
                && $override !== EfectorDemoSeedService::DEFAULT_EFECTOR_REF
                && str_starts_with($codOverride, 'DEV')
                && $codOverride === $codigo
            ) {
                return $override;
            }
            Yii::warning(
                "demo_sandbox.id_efector={$override} ignorado (codigo_sisa={$codOverride}); resolviendo por {$codigo}",
                'demo.sandbox'
            );
        }

        $seed = new EfectorDemoSeedService();
        $row = $seed->findByCodigoSisa($codigo);
        if ($row === null) {
            // Auto-crear plantilla DEV (sin médico permanente) para no caer en efectores reales.
            if ($codigo === EfectorDemoSeedService::COD_SISA_PRIVATE) {
                $created = $seed->upsertClinicaPrivada();
                $row = [
                    'id_efector' => (int) ($created['id_efector'] ?? 0),
                    'codigo_sisa' => $codigo,
                ];
            } elseif ($codigo === EfectorDemoSeedService::COD_SISA_PUBLIC_OTRA_PROV) {
                $created = $seed->upsertPublicOtraProvincia();
                $row = [
                    'id_efector' => (int) ($created['id_efector'] ?? 0),
                    'codigo_sisa' => $codigo,
                ];
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

        self::assertIdEfectorEsPlantillaDev($id, $codigo);

        return $id;
    }

    /**
     * Corta cualquier camino hacia un centro real (p. ej. 863).
     */
    public static function assertIdEfectorEsPlantillaDev(int $idEfector, ?string $codigoEsperado = null): void
    {
        if ($idEfector <= 0) {
            throw new \DomainException('id_efector demo inválido.');
        }
        if ($idEfector === EfectorDemoSeedService::DEFAULT_EFECTOR_REF) {
            throw new \DomainException(
                'La plantilla demo no puede ser el efector real '
                . EfectorDemoSeedService::DEFAULT_EFECTOR_REF
                . '. Sacá id_efector=863 de params-local y usá efector_codigo_sisa=DEV99002PRIV.'
            );
        }

        $ef = Efector::findOne($idEfector);
        if ($ef === null) {
            throw new \DomainException("No existe efectores.id_efector={$idEfector}.");
        }
        $codigo = trim((string) $ef->codigo_sisa);
        if (!str_starts_with($codigo, 'DEV')) {
            throw new \DomainException(
                "El efector {$idEfector} no es plantilla DEV (codigo_sisa={$codigo})."
            );
        }
        if ($codigoEsperado !== null && $codigoEsperado !== '' && $codigo !== $codigoEsperado) {
            throw new \DomainException(
                "El efector {$idEfector} tiene codigo_sisa={$codigo}, se esperaba {$codigoEsperado}."
            );
        }
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array{
     *     servicio_nombre: string,
     *     with_agenda: bool,
     *     rbac_role: string,
     *     apellido: string,
     *     username_prefix: string,
     *     cobertura_classes: list<string>,
     *     cobertura_ttl_seconds: int,
     *     role: string,
     *     pacientes: int,
     *     turnos: int,
     *     with_consulta_amb: bool,
     *     with_consulta_async: bool,
     *     consultas_async: int,
     *     with_guardia: bool,
     *     with_internacion: bool
     * }
     */
    private function staffProvisionSpec(string $staffKind, array $cfg): array
    {
        $seedCfg = is_array($cfg['seed'] ?? null) ? $cfg['seed'] : [];
        $sessionTtl = max(600, (int) ($cfg['session_ttl_seconds'] ?? 14400));
        $pacientes = (int) ($seedCfg['pacientes'] ?? 6);
        $withGuardia = (bool) ($seedCfg['with_guardia'] ?? true);
        $withInternacion = (bool) ($seedCfg['with_internacion'] ?? true);
        $emer = \common\models\Clinical\Encounter::ENCOUNTER_CLASS_EMER;
        $imp = \common\models\Clinical\Encounter::ENCOUNTER_CLASS_IMP;

        if ($staffKind === 'enfermeria') {
            $servicioNombre = trim((string) ($cfg['servicio_enfermeria_nombre'] ?? 'ENFERMERIA'));

            return [
                'servicio_nombre' => $servicioNombre !== '' ? $servicioNombre : 'ENFERMERIA',
                'with_agenda' => false,
                'rbac_role' => 'enfermeria',
                'apellido' => 'Enfermería',
                'username_prefix' => DemoSandboxAccess::usernamePrefixForStaffKind('enfermeria'),
                'cobertura_classes' => [$emer, $imp],
                'cobertura_ttl_seconds' => $sessionTtl,
                'role' => DemoSandboxAccess::ROLE_ENFERMERIA,
                'pacientes' => $pacientes,
                'turnos' => 0,
                'with_consulta_amb' => false,
                'with_consulta_async' => false,
                'consultas_async' => 0,
                'with_guardia' => $withGuardia,
                'with_internacion' => $withInternacion,
            ];
        }

        if ($staffKind === 'administrativo') {
            $servicioNombre = trim((string) ($cfg['servicio_nombre'] ?? 'MED GENERAL'));

            return [
                'servicio_nombre' => $servicioNombre !== '' ? $servicioNombre : 'MED GENERAL',
                'with_agenda' => false,
                'rbac_role' => 'Administrativo',
                'apellido' => 'Admisión',
                'username_prefix' => DemoSandboxAccess::usernamePrefixForStaffKind('administrativo'),
                'cobertura_classes' => [$emer],
                'cobertura_ttl_seconds' => $sessionTtl,
                'role' => DemoSandboxAccess::ROLE_ADMINISTRATIVO,
                'pacientes' => $pacientes,
                'turnos' => 0,
                'with_consulta_amb' => false,
                'with_consulta_async' => false,
                'consultas_async' => 0,
                'with_guardia' => $withGuardia,
                'with_internacion' => $withInternacion,
            ];
        }

        $servicioNombre = trim((string) ($cfg['servicio_nombre'] ?? 'MED GENERAL'));

        return [
            'servicio_nombre' => $servicioNombre !== '' ? $servicioNombre : 'MED GENERAL',
            'with_agenda' => (bool) ($seedCfg['with_agenda'] ?? true),
            'rbac_role' => '',
            'apellido' => 'Médico',
            'username_prefix' => DemoSandboxAccess::usernamePrefixForStaffKind('medico'),
            'cobertura_classes' => [],
            'cobertura_ttl_seconds' => $sessionTtl,
            'role' => DemoSandboxAccess::ROLE_STAFF,
            'pacientes' => $pacientes,
            'turnos' => (int) ($seedCfg['turnos'] ?? 2),
            'with_consulta_amb' => (bool) ($seedCfg['with_consulta_amb'] ?? true),
            'with_consulta_async' => (bool) ($seedCfg['with_consulta_async'] ?? true),
            'consultas_async' => (int) ($seedCfg['consultas_async'] ?? 2),
            'with_guardia' => $withGuardia,
            'with_internacion' => $withInternacion,
        ];
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
