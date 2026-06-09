# Fase 3 — Régimen B (delegación paciente)

**Estado:** implementada

## Objetivo

Paciente designa uno o más representantes (cualquier cuenta); revocación inmediata; sin aceptación del representante.

## API (borrador)

| Método | Ruta | Actor |
|--------|------|-------|
| POST | `/api/v1/person-representation/designar-representante` | Paciente — crea B + Consent active |
| POST | `/api/v1/person-representation/revocar-representante` | Paciente |
| GET | `/api/v1/person-representation/mis-representantes` | Paciente |
| GET | `/api/v1/person-representation/pacientes-a-cargo` | Representante — lista sujetos activos |
| POST | `/api/v1/person-representation/revocar-para-staff` | Staff (misma acción que A) |

## Reglas

- Representante identificado por `id_persona` o documento + búsqueda cuenta.
- Varios representantes: varias filas `active`; misma `provision_json`.
- Designación = `Consent` active de inmediato (decisión B4).

## Preferencia notificaciones (decisión N9)

- [x] Tabla `person_representation_pref` + `PersonRepresentationPreferenceService`
- [ ] Hook al actuar representante (Fase 5) — solo si pref true

## Checklist

- [x] RBAC `m260616_130000_api_person_representation_delegation_rbac.php`
- [x] `PatientDelegationService` + acciones en `PersonRepresentationController`
- [x] Tests `PatientDelegationAccessTest` (revocación → `canAct` false)
- [x] Staff revoca vía `revocar-para-staff` (Fase 2, régimen B incluido)
