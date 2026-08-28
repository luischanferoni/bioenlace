# Fase 0 — Marco y terminología

## Objetivo

Cerrar nomenclatura y catálogo v1 sin código de loaders.

## Entregables

- [ ] Enum documentado: `AssistantContextHISArea` (claves + labels preprocess).
- [ ] Enum documentado: `AssistantContextHISAreaAspect` (claves JSON + área padre).
- [ ] Tabla área → aspectos por defecto (PHP estático en design, luego en enum).
- [ ] Reglas de resolución de anclas (documento en design § AnchorResolver).
- [ ] Decisión: preprocess solo áreas; aspectos solo en PHP post-preprocess.
- [ ] Revisión privacidad: volcado solo sujeto autorizado; límites de filas.

## Glosario publicado

| Termino | Uso |
|---------|-----|
| Área HIS | Preprocess |
| Aspecto | 2ª IA + loaders |
| Entidad | Yii AR interno |
| Ancla | IDs para filtros |
| Volcado | JSON en prompt 2ª IA |

## Criterio de salida

Design y overview aprobados por producto; lista de áreas y aspectos `appointments` cerrada.
