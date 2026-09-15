# Fase 06 — Capas PHP en BC + desarmar Integrations

## Objetivo

Reordenar código PHP hacia Application / Domain / Infrastructure y eliminar `Domain/Integrations` como BC falso.

## 6a — Forma interior por BC (oleadas)

Orden sugerido: `Geo` / `Content` (pequeños) → `Person` → `Organization` → `Scheduling` → `Clinical` (último, más grande).

Por oleada:

1. Crear carpetas de capa.
2. Mover clases con sufijo correcto (`*Agent` → Application, `*Policy`/`*Catalog` → Domain, connectors → Infrastructure/External).
3. Actualizar namespaces + uses.
4. Endurecer test de forma para ese BC.

## 6b — Integrations

1. Mover cada `<Sistema>/` al BC dueño bajo `Infrastructure/External/`.
2. `NavSisse` / widgets → `frontend` (widgets/menú); fuera de Domain.
3. `IntegrationRetryAgent` → Application del BC HistoryExchange/Clinical.
4. Quitar carpeta `Domain/Integrations`; actualizar `ProductDomainCatalog` (ya no lista integrations).
5. Espejo: `models/Integrations` evaluar rename a BC dueño en sub-PR si aplica.

## Criterio de done

- `scandir(Domain)` sin `Integrations`.
- Ningún Widget Yii bajo `components/Domain`.
- Test de forma: roles y sufijos en BC migrados.

## Riesgo

Namespaces masivos: un sistema Integrations por PR.