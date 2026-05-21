# Fase 12 — Frontend Yii: retirar duplicado de consultas

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** Fases 4–11 (API + Clinical + UI JSON ya cubren el canal principal)  
**Estado:** hecho (2026-05-20)

## Contexto real del producto

- **Canal principal:** API v1 + Flutter + web como **cliente** (SPA asistente, `spa-home.js`, UI JSON).
- **Yii web clásico:** no es el lugar donde se desarrolla clínica nueva; quedan **vistas nativas puntuales** (ej. agenda laboral) e **residuos** del modelo `Consulta*`.
- **Sin métricas de tráfico** por ruta Yii: el criterio de esta fase es **paridad funcional con la API ya entregada**, no inventario por logs.

## Objetivo

Eliminar o aislar el **frontend MVC de consultas** que duplica (o contradice) lo ya resuelto en API, sin tocar lo que **no** tiene sustituto API o que es nativo operativo distinto.

## Criterio de decisión (sin analytics)

| Pregunta | Acción |
|----------|--------|
| ¿Existe endpoint API v1 equivalente y las tablas legacy están dropeadas? | **Retirar** controller/vista web `Consulta*` o responder redirect/410 a documentación |
| ¿La API devuelve 410 y el web aún persiste en tablas `consultas_*`? | **Retirar urgente** (código roto o dual-write fantasma) |
| ¿Es flujo operativo sin duplicado clínico (agenda, internación cama, login, SPA)? | **Fuera de alcance** o solo alinear nombres (`encounter_id`) |
| ¿Es informe/PDF que lee SQL legacy? | Revisar caso a caso (puede quedar pendiente post-programa) |

## Lo que la API ya anula (duplicado en `frontend/controllers/Consulta*`)

Capa web clásica orientada a **un encuentro ambulatorio** y sus hijos — sustituida por API:

| Antes (web + tablas) | Hoy (API) |
|----------------------|-----------|
| `ConsultasController` / `views/consultas/*` (ficha consulta) | `clinical/encounter/*`, captura `analizar`/`guardar` |
| `ConsultaMedicamentosController` | `…/medication-requests` |
| `ConsultaPracticasController` | `…/service-requests` |
| `ConsultaDiagnosticosController` | `…/conditions` |
| `ConsultaOdontologia*`, oftalmo v2 | `…/odontology`, `…/ophthalmology` |
| `ConsultaIAController` | `clinical/encounter/analizar` |
| `ConsultaMotivos*`, chat motivos | `motivos-consulta/*` (API) + `AppointmentReasonEntry` |
| `ConsultaChatController` (web) | `consulta-chat/*` (API, `encounter_id`) |
| `POST /consulta/*` | **410 Gone** → `clinical/encounter/*` |

Los ~25 controllers bajo `Consulta*` y `views/consultas/` (50+ PHP) entran en el bucket **retirar o 410**, salvo que un partial siga enlazado desde otra pantalla nativa (entonces solo se desenlaza).

## Lo que NO se anula con la API (mantener en fase 12)

| Área | Motivo |
|------|--------|
| `site/asistente`, `spa-home.js`, API `asistente/enviar` | Canal web principal; no es MVC consulta |
| `ProfesionalAgendaController` + vistas nativas agenda | Operativo RRHH; no duplicado por encounter API |
| `Internacion*Controller` (cama, consumos, bridge fase 8) | Dominio internación; API episode-of-care complementa, no reemplaza toda la UI cama |
| `TurnosController` (web) si aún existe | Scheduling; API `turnos/*` es el sustituto para clientes nuevos |
| `PersonasController`, login, layouts | Infra web |

Fase 12 **no** migra agenda ni internación a `views/clinical/`; como mucho actualiza enlaces rotos que apuntaban a `/consultas/view`.

## Alcance recomendado (fase reducida)

1. **Marcar retirado** en código/docs: módulo clínico ambulatorio vía `Consulta*` web.
2. **Eliminar o 410** controllers `frontend/controllers/Consulta*.php` y carpeta `frontend/views/consultas/` (y `ConsultaTrait` si solo servía a eso).
3. **Grep y limpieza** de enlaces en vistas que quedan (`personas/view`, menús) → SPA, API o mensaje “usar app/API”.
4. **Partials que ya llaman API** (`_formulario_consulta.php` con `clinical/encounter/guardar`): mantener como thin client o mover al shell SPA; no mantener `ConsultasController`.
5. **Opcional:** `InternacionController::actionView` leer bundle FHIR (API interna o `InpatientClinicalQuery`) — mejora nativa, no bloqueante para cerrar fase.
6. **AR `Consulta*` en `common/models`:** fuera del DoD mínimo de fase 12 web; puede ser tarea de limpieza `common/` aparte si nada más referencia.

## Fuera de alcance

- Reescribir captura clínica completa en Yii.
- Rediseño visual backoffice.
- Eliminar todos los AR `Consulta*` del monorepo (puede quedar deuda documentada).

## Definition of Done

- [x] Sin rutas web activas que persistan en `consultas` / `ConsultaMedicamentos` / hijos de tablas dropeadas.
- [x] Documento o comentario en `MIGRATION_STATUS`: “Yii web clínico ambulatorio: retirado; canal = API + SPA + Flutter + nativas (agenda, internación)”.
- [x] Enlaces rotos conocidos corregidos o eliminados (SNOMED → `snowstorm/*`; HC/antecedentes IPS retirados de `personas/view`; balance/régimen en internación; botón Atender sin URL MVC).

## Cierre del programa

- [ ] `MIGRATION_STATUS.md` fases 0–12 coherentes.
- [ ] Release notes interno.

## Siguiente paso

No requiere logs: ejecutar la fase con la tabla “API ya anula” de arriba y un PR de eliminación/410 acotado.
