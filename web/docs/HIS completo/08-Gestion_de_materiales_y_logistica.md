# Gestión de materiales y logística

## Estado actual (según evidencia en el repo)

- **Madurez estimada**: **1–2/4** (Prototipo/Básico).
- **Evidencia más fuerte hoy**: registro de **consumos clínicos** (no logística integral).
  - En internación: `web/common/models/SegNivelInternacionConsumo.php`
  - Suministros de medicación: `SegNivelInternacionSuministroMedicamento.php`, `ConsultaSuministroMedicamento.php`

## Qué parece cubrir hoy

- Capacidad de registrar consumos/entregas ligados a episodios clínicos (internación/consulta).
- Base para luego valorizar y facturar consumos, o integrarlos con stock.

## Brechas para logística HIS “completa”

- **Inventario y depósitos**
  - Depósitos/almacenes, ubicaciones, stock por item, lotes/series, vencimientos.
- **Movimientos**
  - Ingresos/egresos, transferencias, ajustes, auditoría de movimientos.
- **Compras y abastecimiento**
  - Requerimientos, órdenes de compra, recepción, control de proveedores.
- **Circuito hospitalario**
  - Pedidos de sala/servicio, preparación, entrega, devolución, consumo.
- **Integración transversal**
  - Quirófanos (implantes/insumos), farmacia (medicamentos), internación (consumos), facturación (valuación).

## Próximos pasos recomendados

- Definir si se integra a un ERP/logística existente o se construye módulo propio.
- Si se construye:
  - Modelos mínimos: `Deposito`, `Articulo`, `Stock`, `Movimiento`, `Lote`, `PedidoInterno`, `OrdenCompra`, `Recepcion`.
- Enlazar consumos clínicos actuales a “artículos” de stock para tener trazabilidad real.

