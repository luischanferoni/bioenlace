# LIS (Laboratorio)

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **2–3/4** (Básico/Intermedio).
- **Modelos**:
  - `web/common/models/Laboratorio.php`
  - `web/common/models/LaboratorioNbu.php` (indica integración con LIS externo tipo NBU)
- **Controlador / UI**:
  - `web/frontend/controllers/LaboratorioController.php`
  - `web/frontend/views/laboratorio/*`
  - `web/frontend/views/laboratorio-nbu-snomed/*` (mapeos/terminología)
  - Reportes (PDF) en vistas de laboratorio (p.ej. dengue/virus respiratorios).
- **Terminología**:
  - Soporte SNOMED en `web/common/models/snomed/*` (y mapeos específicos).

## Qué parece cubrir hoy

- Registro/consulta de resultados y reportes.
- Integración con un sistema externo (NBU) y normalización terminológica (mapeos).
- Capacidad de presentar resultados en contexto clínico (potencialmente consultable por intents).

## Brechas típicas para un LIS “completo”

- **Ciclo completo de la orden**
  - Pedido (order entry) → toma de muestra → etiquetado → recepción → procesamiento → validación → liberación.
- **Workflow operativo**
  - Estados internos, colas por sector, trazabilidad por muestra, incidencias.
- **Interfases estándar**
  - HL7 (v2), FHIR (DiagnosticReport/Observation/ServiceRequest), integración con analizadores.
- **Catálogo analítico**
  - Catálogo de determinaciones (idealmente LOINC), rangos de referencia, unidades, perfiles.

## Decisión clave: “LIS propio” vs “Integrador de LIS externo”

- **Estrategia A (integrar LIS externo)**: reforzar conectores y mapeos, y enfocarse en UX clínica + interoperabilidad.
- **Estrategia B (LIS propio)**: diseñar todo el modelo de orden/muestra/workflow y construir UI operativa completa.

## Próximos pasos recomendados

- Formalizar el modelo de **orden de laboratorio** (aunque el procesamiento sea externo), para tener trazabilidad HIS.
- Mapear determinaciones a un catálogo (LOINC si aplica) y consolidar rangos/unidades.
- Implementar APIs FHIR/HL7 según el entorno (o “export/import” por integración).

