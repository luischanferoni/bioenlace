# Overview — Forma interna de Domain

## Problema

Las carpetas de primer nivel bajo `Domain/` (`Clinical`, `Scheduling`, …) están claras. **Dentro** de cada dominio se mezclan tres ejes (subdominio de negocio, rol técnico, sistema externo) sin gramática común: nombres de carpeta y de archivo no indican qué agrupan.

## Objetivo

Una sola gramática interna, sin big-bang:

```text
Domain/<Dominio>/
  <Subdominio>/          # área de negocio real (opcional)
    Service/
    Enum/ | Dto/         # opcionales
  Service/               # default del dominio
    <Capacidad>/         # solo si ≥ ~4–5 clases del mismo tema
  Assistant/ | Home/ | DataAccess/ | Presentation/
  metadata/
```

`Integrations/` permanece **primer nivel bajo Domain** (no hermana de `Domain/`), con forma propia por sistema externo.

## Fuera de alcance

- Sacar `Integrations` (u otras) al lado de `Platform/` / `Domain/`.
- Renombrar en masa todos los `*Service` históricos en un solo PR.
- Cambiar el árbol espejo (models / API / metadata) salvo que un move de components lo exija.
