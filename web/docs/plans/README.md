# Planes en ejecución (uso interno)

Espacio **temporal** para programas de trabajo grandes (varias fases, varios PR). Solo existe **mientras se construye**.

## Reglas

1. Al **terminar** la construcción: borrar `plans/<slug>/` por completo.
2. Antes de borrar: dejar lo que siga vigente en `producto/<tema>.md` o `decisions/`.
3. **Ningún** otro archivo en `web/docs/` debe enlazar a rutas bajo `plans/` (ni `README` global, ni `producto/`, ni `his-completo/`). Los planes son para quien ejecuta el programa, no para lectores de documentación estable.

## Planes activos

| Plan | Carpeta | Notas |
|------|---------|--------|
| Receta electrónica (AR) | `receta-electronica/` | Fases 1–2 en producción; repositorio nacional pendiente |
| Interoperabilidad HC FHIR | `interoperabilidad-historia-clinica/` | Fases 1–2 + reconcile; homologación nacional pendiente |
| Urgencias — triage + tablero | `urgencias-triage-tablero/` | Fase 1 (API dominio) en curso; fases 2–5 pendientes |
| Agendamiento FHIR entrante | `fhir-scheduling-inbound/` | Doc producto: [interoperabilidad-agendamiento-fhir.md](../producto/interoperabilidad-agendamiento-fhir.md) |
| Alta cuenta institucional | `alta-cuenta-institucional/` | Self-service AdminEfector + solicitud ministerio + pasarela simulada |
| Perfil de comportamiento en turnos | `perfil-comportamiento-turnos/` | Perfil factual persistido; eventos, materialización, políticas y transparencia |
| Auditoría captura clínica | `auditoria-captura-clinica/` | Trail pipeline + admin superadmin (Fase 1) |
| Auditoría adelantamiento turnos (A03) | `auditoria-adelantamiento-turnos/` | Campañas/ofertas + admin superadmin (Fase 0) |
| Auditoría agentes autónomos | `auditoria-agentes-autonomos/` | agent_run genérico + A04 + resolución |
| Demo sandbox institucional | `demo-sandbox-institucional/` | CTA Probar demo → código un solo uso |
| Captura actor + enfermería | `captura-actor-enfermeria/` | Quitar alias ConsultasConfiguracion; overlay PES + CarePlan; app triage |
| Admisión: identidad + ventanilla | `admision-identidad-ventanilla/` | Ingreso DNI (sin alta libre); Didit; NN; sesión de mostrador |
| RBAC capabilities UI nativa | `rbac-capabilities-ui-nativa/` | Permisos assignables fuera de intents; guardia + encounter + panel |
| Asistente — catalogación unificada | `asistente-catalogacion-unificada/` | 1ª IA + catálogo completo; clara/dudosa/incompletas/fuera_de_his; retira guide |

## Planes archivados (carpeta eliminada)

| Plan | Documentación estable |
|------|------------------------|
| Canal guide (fusión clinical + informational) | [asistente-y-chat.md](../producto/asistente-y-chat.md) + [decisions/asistente-canal-guide.md](../decisions/asistente-canal-guide.md) + QA [asistente-consultas.md](../qa/paciente/asistente-consultas.md) |
| Asistente: canales, chat, info_content | [producto/asistente-y-chat.md](../producto/asistente-y-chat.md) + [producto/contenido-informativo.md](../producto/contenido-informativo.md) + QA [asistente-consultas.md](../qa/paciente/asistente-consultas.md) |
| Contexto HIS asistente (áreas + aspectos) | [producto/asistente-y-chat.md](../producto/asistente-y-chat.md) + [decisions/asistente-contexto-his-areas-aspectos.md](../decisions/asistente-contexto-his-areas-aspectos.md) + [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md) |
| Control/Seguimiento + protocolos | [producto/solicitar-atencion.md](../producto/solicitar-atencion.md) + [decisions/care-protocols-plandefinition-lite.md](../decisions/care-protocols-plandefinition-lite.md) |
| Atención remota y async | [producto/atencion-remota-async.md](../producto/atencion-remota-async.md) |
| Cohortes — asistencia + batch IA | [producto/asistencia-cohortes.md](../producto/asistencia-cohortes.md) |
| Representación paciente (FHIR) | [producto/representacion-paciente.md](../producto/representacion-paciente.md) |
| DataAccess — edición dispersa | `common/components/Platform/Core/DataAccess/README.md` + admin «Consultas staff» |
| Permisos DataAccess staff | `common/components/Platform/Core/DataAccess/README.md` + admin «Consultas staff» |
| RBAC sin webvimark | [arquitectura/rbac-catalogo-permisos.md](../arquitectura/rbac-catalogo-permisos.md) |
| RBAC unificado por intents | [decisions/autorizacion-solo-por-intents.md](../decisions/autorizacion-solo-por-intents.md) + [arquitectura/rbac-catalogo-permisos.md](../arquitectura/rbac-catalogo-permisos.md) |
| Limpieza legacy Yii / modelos / BD | Migraciones y código en repo; sin plan activo |

## Convenciones (solo dentro de `plans/`)

- [overview.md](./overview.md)
- [design.md](./design.md)

## Dónde documentar lo ya construido

| Necesidad | Dónde |
|-----------|--------|
| Narrativa de producto | [producto/](../producto/README.md) |
| Decisiones cerradas | [decisions/](../decisions/README.md) |
| Madurez HIS | [his-completo/](../his-completo/README.md) |
