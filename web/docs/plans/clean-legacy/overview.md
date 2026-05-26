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
| **02b** | Sub-controllers internación MVC, facturación/reportes, encuesta parches | Pendiente (aún cableados) |
| **03** | Modelo `Consulta` y tablas hijas legacy (`consultas`, `consulta_*`) | Pendiente |
| **04** | Turnos MVC residual, nomenclador/referencias, RBAC web | Pendiente |

## Fuera de alcance (por ahora)

- Backend `ConsultasConfiguracionController` (admin `encounter_definition`)
- `PacienteController` + `formulario-consulta` (shell → API encounter)
- `InternacionController` mapa/alta (API + vista híbrida)
- App Flutter / asistente
