# Fase 3 — Repositorio nacional (conexión)

## Qué significa «repositorio nacional»

Es la **conexión** de Bioenlace con el sistema oficial de receta digital (MSAL RDI / Receta Digital Argentina): enviar el Bundle FHIR al registrar la receta emitida y, más adelante, anulaciones o consultas según el contrato del proveedor.

No es solo un campo en BD: es un **conector** intercambiable, igual que los LIS de laboratorio.

## Estado actual (estructura sin envío real)

| Pieza | Ubicación |
|-------|-----------|
| Contrato | `Integrations/Prescription/Contract/RecetaDigitalRepositoryConnector` |
| Conector activo por defecto | `NullRecetaDigitalRepositoryConnector` (no envía) |
| Esqueleto HTTP | `HttpRecetaDigitalRepositoryConnector` (`enabled => false`) |
| Registro | `RecetaDigitalRepositoryRegistry` + `params[recetaDigitalRepository]` |
| Tras emitir | `ElectronicPrescriptionRepositoryService` → evento `repository_sync` |

Para activar el día que haya credenciales: en `params-local.php` cambiar `default` a `msal-rdi`, `enabled => true` y completar `baseUrl` / OAuth.

## QR

Independiente del repositorio: el QR apunta a `GET …/verificar-receta?token=` en Bioenlace.

Requiere `recetaDigitalRepository.verificationPublicBaseUrl` (ej. `https://app.tudominio.com/api/v1`). El PDF usa la etiqueta mPDF `<barcode type="QR" …>`.

## Checklist Fase 3

- [x] Estructura de conector + registry + params
- [x] Hook post-`emitir` con evento de auditoría
- [ ] Implementar POST real al endpoint MSAL
- [ ] Persistir `repository_id` devuelto por el nacional (columna o payload de evento)
- [ ] Reintentos / cola si el repositorio no responde
- [ ] Anulación sincronizada con el repositorio
