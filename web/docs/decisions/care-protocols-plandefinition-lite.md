# Decisiones — protocolos de cuidado (PlanDefinition-lite)

Fecha de registro: 2026-07-20. Actualizado: 2026-07-20 (catálogo en BD).

## Contexto

El paciente necesita controles periódicos y acciones por diagnóstico o perfil (edad/sexo) **sin** abrir un intent aparte ni confundir eso con CarePacks de asistencia IA.

## Decisión

| Tema | Decisión |
|------|----------|
| Entrada paciente | Solo **Solicitar Atención** → motivo **Control/Seguimiento** (hub). |
| Plantilla de protocolo | FHIR **PlanDefinition** en forma **lite**; **fuente de verdad: tabla `care_protocol`**. |
| Alcance | `NATION` (todas las provincias) o `PROVINCE` + `id_provincia`. |
| Match | Códigos CIE + `condition_match` (`none` / `active` / `chronic` / `active_or_chronic`) y/o edad / sexo. |
| Admin | API superadmin (`CareProtocolAdminService`); vacunas/preventivos **no** van hardcodeados — los define el superadmin por jurisdicción. |
| Instancia | En v1 el flow opera con `protocol_id` + draft; **no** materializa CarePlan preventivo automático. |
| Acciones | Declarativas (`outcome`, `draft`); servicios de dominio resuelven; orquestadores sin `if protocol_id`. |
| CarePack / CareCohort | **Fuera de scope** de este hub; packs IA pre/post consulta siguen en su dominio. |
| Nombre en UI (perfil) | **Control recomendado** (`hub_label`); no “CarePack” ni “programa” genérico. |
| Nombre técnico | **Protocolo de cuidado** = PlanDefinition-lite en BD. |

## Alternativas descartadas

| Alternativa | Por qué no |
|-------------|------------|
| Intent propio «Consultas y seguimiento» | Fragmentaba UX; absorbido en Control/Seguimiento. |
| Reusar CarePack como protocolo de control | Dominio distinto (contenido IA por cohorte). |
| Hardcode / YAML en runtime | Viola metadata / capas; vacunas por provincia no escalan en archivo. |

## Consecuencias

- Catálogo y matcher: `CareProtocolCatalogService` (lee BD), `CareProtocolMatcherService`.
- Hub: `ControlSeguimientoHubService` (anclas `cp:`, `diag:`, `prot:`) usa jurisdicción del paciente (`id_provincia_contexto`).
- ABM: `CareProtocolAdminService` + API `/api/v1/clinical/care-protocol/*` (solo superadmin).
- Producto: [solicitar-atencion.md](../producto/solicitar-atencion.md), [consultas-seguimiento.md](../producto/consultas-seguimiento.md).
