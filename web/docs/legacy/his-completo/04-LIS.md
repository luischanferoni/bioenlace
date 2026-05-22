# LIS (Laboratorio) — archive

> **2026:** el LIS interno Yii (`LaboratorioController`, tablas `laboratorio*`, import CSV dengue) fue **retirado**. Integración vigente: [laboratorio/README.md](../../laboratorio/README.md) (pull FHIR, `diagnostic_report` / `observation`).

## Estado histórico (pre-retiro)

- **Madurez estimada**: **2–3/4** (Básico/Intermedio).
- UI Yii de registro/import y reportes PDF (dengue, virus respiratorios).
- Tablas `laboratorio`, `laboratorio_nbu`, `laboratorio_nbu_snomed`, `laboratorio_dengue`, `laboratorio_virus_respiratorios`.

## Qué cubría el módulo antiguo

- Registro/consulta de resultados y reportes.
- Integración NBU y mapeos SNOMED locales.
- Presentación en contexto clínico limitada.

## Brechas típicas para un LIS “completo” (referencia)

- Ciclo completo de la orden (order entry → muestra → validación → liberación).
- Workflow operativo por sector.
- Interfases HL7 / FHIR estándar.
- Catálogo analítico (LOINC, rangos, perfiles).

## Dirección actual en Bioenlace

- **Integrador FHIR** de LIS externos (no LIS propio).
- Orden de laboratorio como evolución futura (trazabilidad HIS); ver [laboratorio/design.md](../../laboratorio/design.md).
