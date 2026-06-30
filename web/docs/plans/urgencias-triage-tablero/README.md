# Plan — Urgencias: triage + tablero operativo

| Campo | Valor |
|-------|--------|
| Slug | `urgencias-triage-tablero` |
| Estado | En ejecución — Fase 1 |
| Dueño | Equipo clínico / API / móvil médico |
| Madurez HIS objetivo | [02 Urgencias](../../his-completo/02-urgencias.md) de ~75 % → ~90 % (triage + tablero; sin facturación ni farmacia) |

## Índice

- [overview.md](./overview.md) — alcance, actores, fuera de alcance
- [design.md](./design.md) — decisiones de dominio, API y clientes
- [phases/00-marco.md](./phases/00-marco.md) — marco clínico-operativo y estados
- [phases/01-dominio-triage-api.md](./phases/01-dominio-triage-api.md) — Fase 1: datos + API triage
- [phases/02-tablero-staff-web-mobile.md](./phases/02-tablero-staff-web-mobile.md) — Fase 2: tablero staff
- [phases/03-flujo-medico-movil.md](./phases/03-flujo-medico-movil.md) — Fase 3: flujo médico móvil-first
- [phases/04-integracion-captura-derivacion.md](./phases/04-integracion-captura-derivacion.md) — Fase 4: atención, derivación, push
- [phases/05-indicadores-auditoria.md](./phases/05-indicadores-auditoria.md) — Fase 5: KPIs y auditoría

## Código existente (punto de partida)

| Área | Ubicación |
|------|-----------|
| Episodio guardia | `common/models/Guardia.php`, tabla `guardia` |
| Web ingreso/listado/libro | `frontend/controllers/GuardiaController.php`, `frontend/views/guardia/` |
| Listado EMER (API) | `frontend/modules/api/v1/controllers/PacientesController.php` (`kind: guardias`) |
| app Personal de Salud (lista básica) | `mobile/personalsalud/lib/screens/home_screen.dart`, `guardia_service.dart` |
| Captura clínica EMER | `Consulta::PARENT_GUARDIA`, `Encounter::ENCOUNTER_CLASS_EMER`, `PatientHistoriaUrl` |
| Sesión ámbito | `set-session` con `encounterClass = EMER` |

## Relacionado (documentación estable, sin enlaces desde otros docs)

- Madurez: [his-completo/02-urgencias.md](../../his-completo/02-urgencias.md)
- Al cerrar el programa: volcar narrativa a `producto/urgencias-guardia.md` y actualizar checklist en `his-completo/02-urgencias.md`
