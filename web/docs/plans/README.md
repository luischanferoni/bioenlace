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
| Interoperabilidad HC FHIR | `interoperabilidad-historia-clinica/` | Código + reconcile listos; homologación / credenciales pendientes |
| Perfil de comportamiento en turnos | `perfil-comportamiento-turnos/` | V1 + shadow; piloto fase 5 pendiente |
| Auditoría captura clínica | `auditoria-captura-clinica/` | Trail pipeline + admin superadmin (Fase 1); volcar a producto y cerrar |

## Planes archivados (carpeta eliminada)

| Plan | Documentación estable |
|------|------------------------|
| Alta cuenta institucional | [alta-cuenta-licencia.md](../producto/alta-cuenta-licencia.md) + [onboarding-comercial-self-service.md](../decisions/onboarding-comercial-self-service.md) |
| Urgencias — triage + tablero | [urgencias-guardia.md](../producto/urgencias-guardia.md) + [his-completo/02-urgencias.md](../his-completo/02-urgencias.md) |
| Admisión: identidad + ventanilla | [registro-paciente.md](../producto/registro-paciente.md) + [representacion-paciente.md](../producto/representacion-paciente.md) |
| RBAC capabilities UI nativa | [autorizacion-capabilities-ui-nativa.md](../decisions/autorizacion-capabilities-ui-nativa.md) + [rbac-catalogo-permisos.md](../arquitectura/rbac-catalogo-permisos.md) |
| Captura actor + enfermería | [captura-clinica.md](../producto/captura-clinica.md) |
| Auditoría adelantamiento turnos (A03) | [agentes-autonomos.md](../producto/agentes-autonomos.md) + [turnos.md](../producto/turnos.md) |
| Auditoría agentes autónomos | [agentes-autonomos.md](../producto/agentes-autonomos.md) |
| Demo sandbox institucional | [alta-cuenta-licencia.md](../producto/alta-cuenta-licencia.md) |
| Agendamiento FHIR entrante | [interoperabilidad-agendamiento-fhir.md](../producto/interoperabilidad-agendamiento-fhir.md) |
| Motivos encounter → FHIR Condition | [encounter-reasons-condition-cc.md](../decisions/encounter-reasons-condition-cc.md) + captura clínica |
| DDD norte Org/Person/Sched | [ddd-norte-modelo-rico.md](../decisions/ddd-norte-modelo-rico.md) + [domain-folder-grammar.md](../decisions/domain-folder-grammar.md) + `Domain/README.md` |
| DDD/CA empaquetado transversal | [ddd-modulo-primero-vs-bc-compacto.md](../decisions/ddd-modulo-primero-vs-bc-compacto.md) + [domain-folder-grammar.md](../decisions/domain-folder-grammar.md) + `Domain/README.md` + [common-components.md](../arquitectura/common-components.md) + `BoundedContextLayerShapeTest` |
| Clinical gramática DDD Application/Domain/Infrastructure | [decisions/domain-folder-grammar.md](../decisions/domain-folder-grammar.md) + `Domain/README.md` + `Domain/Clinical/README.md` + `BoundedContextLayerShapeTest` |
| Clinical módulos de capacidad | [decisions/clinical-modulos-capacidad.md](../decisions/clinical-modulos-capacidad.md) + `Domain/Clinical/README.md` + [common-components.md](../arquitectura/common-components.md) |
| DDD capas + metadata | [decisions/ddd-bounded-contexts-capas-y-metadata.md](../decisions/ddd-bounded-contexts-capas-y-metadata.md) + [arquitectura/common-components.md](../arquitectura/common-components.md) + `Domain/README.md` + `metadata/bioenlace/README.md` |
| Forma interna de Domain | [arquitectura/common-components.md](../arquitectura/common-components.md) + `common/components/Domain/README.md` + [decisions/ddd-bounded-contexts-capas-y-metadata.md](../decisions/ddd-bounded-contexts-capas-y-metadata.md) |
| Asistente — catálogo inteligente | [decisions/asistente-catalogo-inteligente.md](../decisions/asistente-catalogo-inteligente.md) + [producto/asistente-y-chat.md](../producto/asistente-y-chat.md) + [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md) |
| Árbol espejo por dominio | [arquitectura/arbol-espejo-dominios.md](../arquitectura/arbol-espejo-dominios.md) + [arquitectura/common-components.md](../arquitectura/common-components.md) |
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
