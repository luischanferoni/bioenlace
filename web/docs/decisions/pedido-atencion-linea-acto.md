# Pedido de atención: línea asistencial × acto clínico (SNOMED/FHIR)

Fecha de registro: 2026-07-30.

## Contexto

El catálogo `servicios` mezclaba destinos asistenciales (kinesiología, oftalmología) con prestaciones tipadas por el acto (ecografía, mamografía). Eso rompe derivaciones y pedidos del paciente: a veces llega solo el “quién”, a veces solo el “qué”.

## Decisión

1. **`servicios` es la línea asistencial** (HealthcareService): agenda, PES, sesión, EncounterDefinition. Se tipifica (`consulta` | `diagnostico` | `laboratorio` | `procedimiento` | `soporte`) y se codifica con SNOMED/FHIR (`specialty_code` + `specialty_system`). **No** rename masivo de tabla/FKs en este slice.
2. **`actos_clinicos`** es el acto (ServiceRequest.code / Procedure): `code` + `code_system` estándar únicamente (`http://snomed.info/sct`, `http://loinc.org`, value sets FHIR oficiales). **Sin** code system local.
3. **`linea_acto`** puente N:M (global o por efector) para resolver el slot faltante.
4. **`PedidoAtencion`** (DTO + servicio de dominio) une línea × acto × modo; completitud = resoluble a un par agendable. Misma regla para derivación clínica y pedido paciente (hub: raíz `estudio_pedido`).
5. Defaults de acto por modo viven en metadata YAML (`pedido-atencion.yaml`), no en orquestadores.
6. Hub paciente: `PedidoAtencionPacienteService` + paso `pedido_acto` en triage; lista de servicios filtrada por `pedido_linea_ids`.

## Alternativas descartadas

- Rename big-bang `servicios` → `lineas_asistenciales`: blast radius ~200 archivos / `id_servicio`.
- Slugs locales (`eco_abdominal`) como código de negocio: rompe alineación SNOMED/FHIR.
- Seguir usando solo `id_servicio` en derivaciones: no modela “te derivo para ecografía”.

## Consecuencias

- Migración tipifica filas existentes y siembra actos/puentes mínimos.
- `DerivacionInput` / `ReferralRequestService` delegan completitud y persistencia de `code`/`code_system` al resolver.
- Hub paciente (`necesito-atencion`) se cablea en un slice posterior con el mismo servicio.
- Nomenclador legacy `practicas` (aranceles) no se migra aquí.

## Referencias

- Código: `common/components/Domain/Clinical/Access/`, `common/models/Clinical/ActoClinico.php`, `common/models/Clinical/LineaActo.php`
- Metadata: `common/metadata/bioenlace/clinical/pedido-atencion.yaml`
- Producto: conversación de diseño línea × acto; [fhir-clinical.md](./fhir-clinical.md)
