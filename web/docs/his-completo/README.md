# HIS completo — mapa de madurez

Documento de **producto y cobertura**, no manual técnico. Responde: *¿qué parte de un hospital information system tenemos hoy y qué falta?*

**Última revisión:** 2026-05 (alineado a laboratorio FHIR, receta electrónica Fase 2, resumen de atención paciente, expediente legal staff, care plans con recordatorios).

Escala por módulo (orientativa):

| Nivel | Significado |
|-------|-------------|
| 0 | No existe en producto |
| 1 | Prototipo o muy parcial |
| 2 | Básico operativo |
| 3 | Intermedio |
| 4 | Avanzado / estándar hospitalario pleno |

## Resumen de completitud

| Módulo | Nivel (0–4) | % módulo |
|--------|-------------|----------|
| [01 Quirófanos](./01-quirofanos.md) | 2 | 50 % |
| [02 Urgencias](./02-urgencias.md) | 3 | 75 % |
| [03 Internación](./03-internacion.md) | 2,5 | 63 % |
| [04 Laboratorio (LIS)](./04-lis.md) | 2,5 | 63 % |
| [05 Farmacia](./05-farmacia.md) | 1,5 | 38 % |
| [06 Receta electrónica](./06-receta-electronica.md) | 3 | 75 % |
| [07 Servicios y especialidades](./07-servicios-y-especialidades.md) | 3 | 75 % |
| [08 Materiales y logística](./08-materiales-y-logistica.md) | 1,5 | 38 % |
| [09 Facturación y contabilidad](./09-facturacion-y-contabilidad.md) | 1,5 | 38 % |
| [10 Atención ambulatoria (FHIR)](./10-atencion-ambulatoria.md) | 3 | 75 % |
| [11 Agenda y turnos](./11-agenda-turnos.md) | 3 | 75 % |
| [12 Planes de tratamiento](./12-planes-tratamiento.md) | 2,5 | 63 % |

**Promedio orientativo del mapa (12 módulos): ~61 %** hacia un HIS hospitalario “completo”.

Interpretación: Bioenlace está **fuerte en consulta ambulatoria, agenda, urgencias parcial, integración LIS externa y receta emitida**; **débil en farmacia dispensación, logística, facturación plena y quirófano avanzado**. El porcentaje no es certificación ni auditoría: es una brújula interna para priorizar producto.

## Módulos (detalle)

| Módulo | Archivo |
|--------|---------|
| Quirófanos | [01-quirofanos.md](./01-quirofanos.md) |
| Urgencias | [02-urgencias.md](./02-urgencias.md) |
| Internación | [03-internacion.md](./03-internacion.md) |
| Laboratorio (LIS) | [04-lis.md](./04-lis.md) |
| Farmacia | [05-farmacia.md](./05-farmacia.md) |
| Receta electrónica | [06-receta-electronica.md](./06-receta-electronica.md) |
| Servicios y especialidades | [07-servicios-y-especialidades.md](./07-servicios-y-especialidades.md) |
| Materiales y logística | [08-materiales-y-logistica.md](./08-materiales-y-logistica.md) |
| Facturación y contabilidad | [09-facturacion-y-contabilidad.md](./09-facturacion-y-contabilidad.md) |
| Atención ambulatoria | [10-atencion-ambulatoria.md](./10-atencion-ambulatoria.md) |
| Agenda y turnos | [11-agenda-turnos.md](./11-agenda-turnos.md) |
| Planes de tratamiento | [12-planes-tratamiento.md](./12-planes-tratamiento.md) |

## Informe para lectura externa (PDF)

Versión en lenguaje de negocio, sin jerga técnica, para producto e inversión:

**[informe-ejecutivo.md](./informe-ejecutivo.md)** → exportar a PDF (ver sección final del informe).

## Cómo usarlo

- Validar con clínica y negocio los ítems “tenemos / falta”.
- Cruzar con [producto/](../producto/README.md) para el recorrido operativo de cada área.
