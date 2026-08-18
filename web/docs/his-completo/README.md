# HIS completo — mapa de madurez

Documento de **producto y cobertura**, no manual técnico. Responde: *¿qué parte de un hospital information system tenemos hoy y qué falta?*

**Última revisión:** 2026-08-18 — alineado a [producto/](../producto/README.md): Solicitar Atención, teleconsulta y consulta por mensaje, journey pre/post, agentes (lab, agenda, internación), receta con gate de integridad, captura unificada AMB/EMER/IMP.

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
| [02 Urgencias](./02-urgencias.md) | 4 | 95 % |
| [03 Internación](./03-internacion.md) | 3,4 | 85 % |
| [04 Laboratorio (LIS)](./04-lis.md) | 2,7 | 68 % |
| [05 Farmacia](./05-farmacia.md) | 1,5 | 38 % |
| [06 Receta electrónica](./06-receta-electronica.md) | 3 | 75 % |
| [07 Servicios de salud (oferta del centro)](./07-servicios-y-especialidades.md) | 3,2 | 80 % |
| [08 Materiales y logística](./08-materiales-y-logistica.md) | 1,5 | 38 % |
| [09 Facturación y contabilidad](./09-facturacion-y-contabilidad.md) | 1,5 | 38 % |
| [10 Atención ambulatoria](./10-atencion-ambulatoria.md) | 3,4 | 85 % |
| [11 Agenda y turnos](./11-agenda-turnos.md) | 3,5 | 88 % |
| [12 Planes de tratamiento](./12-planes-tratamiento.md) | 3,3 | 83 % |

**Promedio orientativo del mapa (12 módulos): ~69 %** hacia un HIS hospitalario “completo”.

Interpretación: Bioenlace está **fuerte en consulta ambulatoria (puerta digital + captura + resumen), agenda (autogestión, teleconsulta, agentes de cupo y no-show), guardia operativa e internación (mapa, alta en captura, seguimiento post-alta)**; **operativo en laboratorio externo (aviso al paciente) y receta emitida**. Sigue **débil en farmacia con dispensación, logística, facturación plena y quirófano avanzado**. El porcentaje no es certificación ni auditoría: es una brújula interna para priorizar producto.

### Qué se movió respecto de mayo 2026

| Área | Antes | Ahora | Por qué |
|------|-------|-------|---------|
| Agenda | 81 % | 88 % | Teleconsulta, triage, adelantamiento, reubicación, anti no-show, representación |
| Ambulatorio | 75 % | 85 % | Solicitar Atención, journey, mensaje/video, codificación, export FHIR |
| Planes | 75 % | 83 % | Hub Control/Seguimiento, consulta por mensaje, ramas post-cuestionario |
| Internación | 82 % | 85 % | Sugerencia de cama, seguimiento post-alta, captura unificada |
| Laboratorio | 63 % | 68 % | Aviso al paciente al liberar resultado; vínculo informe–atención |
| Servicios | 75 % | 80 % | Acto → oferta del centro; política de teleconsulta por servicio |

Quirófano, farmacia, materiales y facturación **no subieron**: el producto no cerró esos circuitos.

## Informe para lectura externa

Versión en lenguaje de negocio: [informe-ejecutivo.md](./informe-ejecutivo.md).

## Cómo usarlo

- Validar con clínica y negocio los ítems “tenemos / falta”.
- El recorrido operativo de cada área está en [producto/](../producto/README.md), no aquí.
