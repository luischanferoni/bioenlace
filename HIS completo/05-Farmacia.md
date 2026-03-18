# Farmacia

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **2–3/4** (Básico/Intermedio).
- **Prescripción y medicación clínica (ambulatoria)**:
  - `web/common/models/ConsultaMedicamentos.php`
  - Vistas de prescripción en `web/frontend/views/consultas/prescripciones_medicas.php` (y formularios relacionados).
- **Medicación en internación**:
  - `web/common/models/SegNivelInternacionMedicamento.php`
  - `web/common/models/SegNivelInternacionSuministroMedicamento.php`
  - `web/common/models/ConsultaSuministroMedicamento.php`
- **Interoperabilidad (estilo FHIR)**:
  - `web/common/models/bundles/Resource.php`
  - `web/common/models/bundles/MedicationRequest.php`
  - `web/common/models/bundles/Prescripcion.php`
- **Reportes**:
  - `web/frontend/views/reporte/formReporteFarmacia.php` (indicio de reporting farmacéutico).

## Qué parece cubrir hoy

- Indicación/prescripción de medicamentos en consultas.
- Indicación y suministro/administración durante internación.
- Base interoperable para comunicar órdenes de medicación (bundles tipo FHIR).

## Brechas para una farmacia HIS “completa”

- **Gestión de stock y depósitos**
  - Inventario, múltiples depósitos, vencimientos, lotes/series, equivalencias.
- **Dispensación y validación farmacéutica**
  - Validación de prescripciones, sustituciones, alertas (interacciones, dosis).
  - Registro de dispensación (ambulatorio) y entrega por sala (internación).
- **Compras y abastecimiento**
  - Pedidos internos, órdenes de compra, recepción, conciliación con contabilidad.
- **Trazabilidad**
  - Auditoría de movimientos, cadena de custodia, consumos valorizados.

## Próximos pasos recomendados

- Elegir estrategia:
  - **Integrar con farmacia/ERP existente** (rápido, menos riesgo) o
  - **Construir módulo de stock+dispensación** (más control, más esfuerzo).
- Si se construye:
  - Modelar `Deposito`, `StockItem`, `MovimientoStock`, `Lote`, `Vencimiento`, `Dispensacion`, `PedidoInterno`.
- Consolidar el circuito clínico: **prescripción → validación → dispensación/entrega → administración → auditoría**.

