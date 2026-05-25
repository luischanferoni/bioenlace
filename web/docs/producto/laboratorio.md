# Laboratorio (resultados externos)

## De qué se trata

Bioenlace **no es un LIS propio**. Se conecta a **laboratorios externos** que publican resultados en estándar clínico (FHIR: informes y analitos). El sistema **trae** esos datos, los **guarda** y el paciente o el equipo los **consultan** en contexto de atención.

## Actores

- **Operaciones / cron:** ejecutan sincronización periódica o por lote.
- **Paciente:** ve listado y detalle en Bioenlace (solo lectura de lo ya importado).
- **Profesional:** ve resultados vinculados al encounter cuando atiende.

## Cómo funciona (ingesta → consulta)

```mermaid
flowchart LR
  CRON[Cron o consola]
  EXT[Servicio LIS externo]
  SYNC[Lógica de sincronización]
  DB[(Informes normalizados)]
  API[API clínica]
  PAC[Paciente / médico]
  CRON -->|HTTP autenticado| EXT
  EXT --> SYNC
  SYNC --> DB
  PAC --> API
  API --> DB
```

1. **Programación:** un job (cron o comando de consola) recorre pacientes o lotes configurados.
2. **Request externo:** se llama al proveedor (por ejemplo Sianlabs) con credenciales globales por institución.
3. **Normalización:** cada informe y analito se persiste en tablas clínicas unificadas.
4. **Consulta:** el paciente pide “mis resultados” → la API lista lo ya almacenado; el detalle puede incluir enlace a la **atención** donde se pidió el estudio.
5. **PDF:** la descarga se genera en servidor cuando el usuario lo solicita (no es un archivo que “sube” el LIS directo al teléfono).

## Qué no hace hoy

- Alta manual de resultados en pantallas Yii antiguas (módulo retirado).
- Push automático desde el LIS al publicar (opcional futuro).
- Ciclo completo de orden de laboratorio dentro del HIS (pedido → muestra → validación en planta).

## Relación con el resto

- Pedidos de estudio en el **encounter** y resumen de **atención paciente** enlazan al informe cuando existe.
- Madurez frente a un LIS hospitalario completo: [his-completo/04-lis.md](../his-completo/04-lis.md).
