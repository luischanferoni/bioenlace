# Overview — clean-legacy

## Problema

Quedaron capas **Yii MVC** (controllers, vistas, rutas API 410), widgets de inicio obsoletos y módulos aislados (COVID, demo receta) después de migrar:

- Captura clínica → `POST /api/v1/clinical/encounter/*`
- Guardia operativa → `GET/POST /api/v1/clinical/emergency-guardia/*` + `site/pacientes` (EMER)
- Turnos paciente/staff → `api/v1/turnos/*` + asistente

## Alcance del programa

| Capa | Acción |
|------|--------|
| `frontend/controllers`, `frontend/views` | Eliminar o reducir según checklist |
| `frontend/modules/api/v1/controllers` | Quitar stubs 410 sin clientes |
| `common/models`, `common/models/busquedas` | Eliminar cuando no haya referencias |
| Migraciones Yii | `dropTable` / `dropColumn` en fases posteriores |
| RBAC / `auth_item` rutas fantasma | Limpiar tras quitar controllers |

## Fases previstas

| Fase | Contenido | Estado |
|------|-----------|--------|
| **01** | Eliminación segura + candidatos fuertes | Hecho |
| **02** | COVID (modelos + BD), vistas huérfanas enfermería, modelo duplicado `AtencionesEnfermeria` | Hecho |
| **02b** | Sub-controllers internación MVC clínico, facturación/reportes, encuesta parches | Parcial — clínico IMP 410 (03d); hcama/ingreso pendiente flow |
| **03** | Desacople guardia (`Encounter`), huérfanos `Consulta*`; bloqueo núcleo `Consulta` documentado | Hecho (parcial) |
| **03b** | Encuesta parches → `Encounter`, `ConsultaAtencionesEnfermeria.encounter_id`, constantes en turnos/listado | Hecho (parcial) |
| **03c** | Retiro `Consulta` + shim, drop tabla `consultas` | Hecho |
| **03d** | Retiro MVC clínico internación → timeline + encounter IMP | Hecho |
| **03e** | Tablas hijas legacy → FHIR + drop `m260526_150002` | **Activa** — [NEXT.md](./NEXT.md) |
| **04** | Turnos MVC residual, RBAC web | Pendiente |

## Fuera de alcance (por ahora)

- Backend `ConsultasConfiguracionController` (admin `encounter_definition`)
- `PacienteController` + `formulario-consulta` (shell → API encounter) — **camino único de captura** (AMB/EMER/IMP)
- `InternacionController` index/ronda/view admin + API — **sin** pestañas clínicas MVC (03d)
- App Flutter / asistente
