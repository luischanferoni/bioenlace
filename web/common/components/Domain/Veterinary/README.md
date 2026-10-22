# Dominio Veterinary

Vertical propia para atención animal; no reutiliza `Clinical` humano ni convierte `Persona` en animal. Mantiene la gramática módulo-primero descrita en [Domain README](../README.md):

```text
Veterinary/
  Animal/
    Application/{UseCase,Service,Seed}/
    Domain/{Model,Catalog,Port}/
    Infrastructure/{Persistence,External}/
```

El módulo inicial `Animal` será dueño del registro básico del animal, su vínculo con el tutor y los catálogos de especie y raza. La persistencia y las semillas deben tener una única fuente de verdad. No agregar rutas ni controladores clínicos veterinarios aquí hasta aprobar el MVP.

La captura clínica solo podrá reutilizarse mediante una referencia genérica de sujeto (tipo e identificador); los casos humanos existentes conservan su comportamiento y su relación con `Persona`.
