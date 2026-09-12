# Domain/Integrations

Adapters hacia sistemas **externos** (borde del producto). Namespace: `common\components\Domain\Integrations\…`.

## Forma canónica

```text
Domain/Integrations/<Sistema>/
  Contract/     # interfaces
  Connector/    # HTTP / null / vendor
  Mapper/       # formatos externos ↔ modelo interno
  Service/      # sync / reconcile del borde (opcional)
  Dto/
  Exception/
```

El 2.º nivel es el **sistema o capacidad de integración** (Mpi, Laboratory, Prescription, …), no un subdominio clínico.

La orquestación de negocio que consume estos connectors vive en `Clinical/`, `Scheduling/`, `Person/`, etc.

Gramática general de Domain: [../README.md](../README.md).
