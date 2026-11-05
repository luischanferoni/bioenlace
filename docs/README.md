# Documentación Bioenlace

Historias de producto, arquitectura y pruebas. No es un índice de archivos del repositorio ni el detalle de un endpoint.

## Dónde está cada cosa

| Carpeta | Para qué sirve |
|---------|----------------|
| [producto/](producto/README.md) | Cómo funciona cada área de punta a punta |
| [qa/](qa/README.md) | Cómo probar: escenarios clínicos y checklists por rol |
| [arquitectura/](arquitectura/README.md) | Piezas transversales (asistente, metadata, RBAC) |
| [his-completo/](his-completo/README.md) | Madurez hacia un HIS hospitalario (qué hay / qué falta) |
| [decisions/](decisions/README.md) | Decisiones técnicas cerradas (ADR) |
| [operacion/](operacion/cron-produccion-hostinger.md) | Cron en producción e [integración de Mercado Pago](operacion/mercado-pago-checkout-pro.md) |
| [negocio/costos/](negocio/costos/README.md) | Costos de IA e infraestructura |
| [negocio/modelo-de-negocio/](negocio/modelo-de-negocio/README.md) | Casos de mercado, vías de ingreso y [business plan](negocio/modelo-de-negocio/business-plan/README.md) |
| [negocio/presentaciones/](negocio/presentaciones/README.md) | Decks comerciales |
| [negocio/legal/](negocio/legal/) | Formularios y comprobantes (ARCA/AFIP, planillas societarias) |
| [brand/](brand/) | Logo e identidad visual |
| [mobile/](mobile/) | Design system y assets de Play Store de las apps Flutter |

`plans/` es **temporal**: programas grandes mientras se construyen. Al cerrar, lo vigente queda en `producto/` o `decisions/` y se borra la carpeta. No enlazar `plans/` desde el resto de esta documentación. Índice interno: [plans/README.md](plans/README.md).

## Convención

- Archivos en **minúsculas** y **kebab-case**.
- Producto y arquitectura: lenguaje natural, diagramas; sin bloques de código.
- Los flujos de usuario viven en `producto/` (narrativa) y `qa/` (pasos para probar).
