# Person / Representation

Representación operativa paciente — régimen A (tutela verificada) y régimen B (delegación paciente → representante).

Documentación de producto: [web/docs/producto/representacion-paciente.md](../../../../docs/producto/representacion-paciente.md).

## Servicios

| Servicio | Responsabilidad |
|----------|-----------------|
| `PersonRepresentationAccessService` | `canAct(actor, subject, permission)` |
| `PersonRepresentationSubjectService` | Resolver sujeto, sesión `subjectPersonaPaciente`, auditoría delegada |
| `VerifiedGuardianshipService` | Régimen A: solicitar, listar, staff verificar/bloquear/revocar |
| `PatientDelegationService` | Régimen B: designar, revocar, listar representantes / pacientes a cargo |
| `PersonRepresentationPreferenceService` | Preferencia N9 `notify_on_representative_action` |
| `PersonRepresentationDelegatedActionNotifier` | Push + inbox tras acción delegada (si N9 activo) |
| `PersonRepresentationMpiService` | Alta/resolución de menor vía MPI |
| `PersonRepresentationPresenter` | Serialización API de vínculos |

## Metadata

- Permisos v1: `metadata/representation_permissions_v1.yaml`
- Enums: `Enum/RepresentationPermission.php`, `RepresentationRegime.php`, …

## API (`PersonRepresentationController`)

Prefijo RBAC: `/api/person-representation/<action>`.

| Acción | Uso |
|--------|-----|
| `solicitar-menor-como-tutor` | POST — régimen A, alta pending |
| `mis-vinculos-como-tutor` | GET\|POST — listado tutor |
| `designar-representante` | POST — régimen B |
| `revocar-representante` | POST — paciente revoca |
| `mis-representantes` | GET\|POST — designados por el paciente |
| `pacientes-a-cargo` | GET\|POST — sujetos donde soy representante |
| `establecer-sujeto-paciente` | POST — contexto sesión web |
| `preferencias-como-paciente` | GET\|POST — N9 |
| `verificar-vinculo-para-staff` | POST — staff |
| `bloquear-para-staff` | POST — staff |
| `revocar-para-staff` | POST — staff |
| `vinculos-paciente-para-staff` | GET\|POST — staff |

## Sujeto en requests

Parámetros aceptados: `subject_persona_id` o `id_persona_sujeto`.

Orden de resolución (`PersonRepresentationSubjectService`):

1. Request (body/query)
2. Sesión `subjectPersonaPaciente`
3. Actor (`getIdPersona()`)

Controllers de turnos, motivos, care-packs, care plans y HC paciente llaman a `resolveAndAuthorize` o `assertCanAct` según el endpoint.

## Auditoría

Tabla `person_related_audit_log`. Acciones delegadas: `turno_created`, `turno_cancelled`, `motivos_sent`, `care_pack_assistance`, `historia_accesed`, `care_plan_accessed`.

## Asistente

- `Assistant/Catalog/PersonRepresentationUiActionCatalog.php`
- Intents: `personas.vincular-menor-flow.yaml`, `personas.designar-representante-flow.yaml`

## Móvil (Flutter)

- `mobile/packages/shared/lib/person/person_representation_context.dart` — contexto global
- `mobile/paciente/lib/screens/person_representation_hub_screen.dart` — gestión
- `NativeScreenRouter`: `person_representation_hub`

## Migraciones

- `m260616_100000_person_representation_fhir.php` — tablas base
- `m260616_110000_api_person_representation_rbac.php` — tutela + staff
- `m260616_120000_person_representation_preference.php` — prefs N9
- `m260616_130000_api_person_representation_delegation_rbac.php` — delegación
- `m260616_140000_api_person_representation_subject_rbac.php` — establecer sujeto
