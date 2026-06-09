# Fase 2 — Régimen A (tutela verificada por staff)

**Estado:** implementada

## Objetivo

Padre/madre/tutor opera por menor **sin cuenta**; staff verifica y puede bloquear/revocar.

## API (borrador)

| Método | Ruta | Actor |
|--------|------|-------|
| POST | `/api/v1/person-representation/solicitar-menor-como-tutor` | Tutor (cuenta) — alta pendiente |
| POST | `/api/v1/person-representation/verificar-vinculo-para-staff` | Staff |
| POST | `/api/v1/person-representation/bloquear-para-staff` | Staff — orden legal |
| POST | `/api/v1/person-representation/revocar-para-staff` | Staff |
| GET | `/api/v1/person-representation/mis-vinculos-como-tutor` | Tutor — hijos activos |
| GET | `/api/v1/person-representation/vinculos-paciente-para-staff` | Staff — por `id_persona` |

## Flujo menor nuevo

1. Tutor envía DNI + datos hijo (o `id_persona` existente).
2. RENAPER/MPI si persona no existe.
3. `person_related` `pending` → staff `active` + `verified_by=staff`.
4. Tutor legal: `requires_legal_document` — adjunto en `evidence_json`.

## Checklist

- [x] RBAC `m260616_110000_api_person_representation_rbac.php`
- [x] `PersonRepresentationMpiService` (RENAPER/MPI vía `Yii::$app->mpi`)
- [x] `VerifiedGuardianshipService` + `PersonRepresentationController`
- [x] Rutas en `frontend/config/main.php`
- [x] Auditoría (`link_requested`, `link_verified`, `link_blocked`, `link_revoked`)
- [ ] UI admin web (Fase 2b opcional; staff consume JSON API)

## Fuera de fase

- Crear turno por hijo (Fase 4)
- App móvil selector (Fase 5)
