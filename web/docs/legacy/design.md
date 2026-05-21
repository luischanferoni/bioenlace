# Design — Legacy

## Por qué `legacy/his-completo/`

Inventario por dominio (quirófanos, LIS, farmacia, etc.) como **mapa de brechas**, desactualizado respecto al código actual. Vive bajo `legacy/` para no mezclarlo con dominios operativos en la raíz de `docs/`.

**Alternativa descartada:** fusionar cada `0N-*.md` en dominios operativos — mezclaría “estado deseado HIS” con “cómo funciona turnos hoy”.

## Quirófano: consulta unificada

Texto clínico nuevo va por `ConsultaController::actionGuardar` (Nivel 1). Columnas `procedimiento_descripcion` / `observaciones` en `cirugia` son solo lectura legacy en API.

Ver [flows/quirofano-consulta-legacy.md](./flows/quirofano-consulta-legacy.md).
