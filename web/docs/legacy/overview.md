# Overview — Legacy

## Qué es

Material que describe **cómo era** o **qué falta** respecto a un HIS hospitalario completo, sin mezclarlo con guías operativas de API y asistente.

## Objetivo

- Evitar que desarrolladores nuevos confundan análisis de brechas (madurez 0–4) con contratos vigentes.
- Centralizar notas de migración puntual (p. ej. texto en `cirugia` vs. `consultas`).

## Actores

- Arquitectura / liderazgo técnico (lectura de brechas HIS).
- Backend al migrar datos legacy de quirófano.

## Alcance

| Incluido | No incluido |
|----------|-------------|
| Carpeta [his-completo](./his-completo/00-README.md) | Roadmap activo → `plans/` |
| Quirófano columnas legacy | Flujos nuevos de reserva/captura |
