# Facturación y contabilidad

## Estado actual (según evidencia en el repo)

- **Madurez estimada**:
  - **Facturación**: **2–3/4** (Básico/Intermedio).
  - **Contabilidad**: **1/4** (Prototipo/Ausente como módulo contable completo).
- **Piezas evidentes de facturación**
  - Controlador: `web/frontend/controllers/AutofacturacionController.php`
  - Modelos: `web/common/models/sumar/AutofacturacionBusqueda.php`
  - Nomencladores: `web/common/models/NomencladorSumar.php` (y vistas relacionadas)
  - Vistas: `web/frontend/views/autofacturacion/*` (procesadas/no procesadas, mapeos, seguimiento).

## Qué parece cubrir hoy

- Flujo de **autofacturación** vinculado a actividad asistencial (consultas/prácticas).
- Seguimiento de qué está facturado/procesado vs. no procesado.
- Base para interoperar con esquemas de facturación (ej. SUMAR).

## Brechas hacia “ciclo financiero completo” (HIS completo)

- **Ciclo de facturación completo**
  - Emisión de comprobantes, reglas impositivas locales, notas de crédito/débito, re-facturación.
- **Cobranzas y cuentas corrientes**
  - Seguimiento de pagos, convenios, deudores, conciliaciones.
- **Contabilidad**
  - Plan de cuentas, asientos, integración bancaria, cierres, centros de costo.
- **Integración con logística**
  - Valorización de consumos (materiales/medicación), imputación a facturación y contabilidad.

## Próximos pasos recomendados

- Definir estrategia:
  - **Integrar** con sistema contable/financiero existente (más común) o
  - **Construir** contabilidad dentro del HIS (proyecto mayor).
- Consolidar una capa de “hechos de facturación” desde lo clínico:
  - Consulta/Internación/Quirófano/Lab/Farmacia → items facturables con trazabilidad, codificación y valuación.
- Reforzar nomencladores y reglas de negocio de facturación según financiador.

