# Pedido de atención: servicio institucional × acto clínico (SNOMED/FHIR)

Fecha de registro: 2026-07-30.  
Aclaración semántica: 2026-07-31 — ver [producto/glosario-servicio-pes-acto.md](../producto/glosario-servicio-pes-acto.md).  
Capacidad ECL: 2026-07-31.

## Contexto

El catálogo `servicios` debe modelar la **oferta de salud del efector** (HealthcareService). Mezclar ahí destinos institucionales (kinesiología, oftalmología) con prestaciones tipadas por el acto (ecografía, mamografía) confunde pedidos: a veces llega solo el área, a veces solo el qué.

## Decisión

1. **`servicios` = servicio de salud institucional** (oferta del centro). Agenda, PES, sesión, EncounterDefinition. Se tipifica (`consulta` | `diagnostico` | `laboratorio` | `procedimiento` | `soporte`) y puede llevar `specialty_code` / `specialty_system` como **tipología de esa oferta** (no “especialidad del PES”). **No** rename masivo de tabla/FKs en el slice inicial.
2. **PES** = profesional asignado a ese servicio del efector; no redefine el acto clínico.
3. **`actos_clinicos`** = caché de acto (ServiceRequest.code): `code` + `code_system` estándar (`http://snomed.info/sct`, `http://loinc.org`, value sets FHIR). **Sin** code system local. Fuente de verdad: Snowstorm.
4. **Capacidad acto → oferta** = **ECL por tipología** (`capacity_rules` en `pedido-atencion.yaml`, membership vía Snowstorm) **∪** puente **`linea_acto`** (excepciones / preferentes). Preferente del puente gana.
5. **`oferta_modelo`**: `institucional` (default) | `legacy_acto` (filas históricas tipo ECOGRAFIA/MAMOGRAFIA). No se borran IDs; no se ofertan como área en hub.
6. **`PedidoAtencion`** (DTO + dominio) une servicio institucional × acto × modo; completitud = par resoluble. Misma regla para derivación clínica y pedido paciente (hub: raíz `estudio_pedido`).
7. **Canales** alimentan el DTO (staff: `analisis-consulta` → `DerivacionInput`; paciente: chips / match liviano). **Coding** de Acto display → SNOMED es dominio (`PedidoAtencionActoCodingService`), no otro cerebro IA. Display sin code **no** se tapa con default de modo.
8. Defaults de acto por modo en metadata YAML (`pedido-atencion.yaml`), no en orquestadores — solo si no hay display pendiente de tipificar.
9. Hub paciente: `PedidoAtencionPacienteService` + paso `pedido_acto`; lista filtrada por `pedido_linea_ids` (= `id_servicio` institucional).

## Alternativas descartadas

- Rename big-bang `servicios` → `lineas_asistenciales`: blast radius alto; se aclara con glosario + COMMENT + lenguaje de producto.
- Slugs locales (`eco_abdominal`) como código de negocio: rompe alineación SNOMED/FHIR.
- Seguir usando solo `id_servicio` en derivaciones sin acto: no modela “te derivo para ecografía”.
- Tratar `specialty_code` como identidad del profesional: confunde oferta del centro con matrícula.
- Capacidad solo por nombre de fila (`ECOGRAFIA`): frágil; se reemplaza por ECL + tipología.

## Consecuencias

- Migración tipifica filas, siembra actos/puentes, marca `legacy_acto` y remapea puentes ECO/MAMO al contenedor imaging.
- `CompositeLineaActoCatalog` = `DbLineaActoCatalog` + `EclCapacityCatalog`.
- `DerivacionInput` / `ReferralRequestService` delegan completitud y `code`/`code_system` al resolver.
- Hub paciente cableado al mismo contrato de pedido; no lista `legacy_acto` como área.
- Nomenclador legacy `practicas` (aranceles) no se migra aquí.
- Docs de producto deben decir **servicio del centro** / **área**, no “especialidad” como sinónimo de `servicios`.

## Referencias

- Glosario: [producto/glosario-servicio-pes-acto.md](../producto/glosario-servicio-pes-acto.md)
- Código: `common/components/Domain/Clinical/Access/` (`CompositeLineaActoCatalog`, `EclCapacityCatalog`, `PedidoAtencionService`)
- Metadata: `common/metadata/bioenlace/clinical/pedido-atencion.yaml`
- [fhir-clinical.md](./fhir-clinical.md)
