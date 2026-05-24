# Fase 2 — PDF, verificación y UI paciente

## Objetivo

Receta emitida **consultable y descargable** por el paciente; integridad documental (hash + token); PDF con código de verificación. Firma digital homologada queda para proveedor externo (Fase 2b).

## Checklist

- [x] Columnas `verification_token`, `document_hash`, `signature_provider`, `signed_at`
- [x] Generación al emitir
- [x] `ElectronicPrescriptionPdfService`
- [x] `GET descargar-pdf-como-paciente`
- [x] `GET verificar-receta?token=` (resumen para farmacia / control)
- [x] UI JSON listado + detalle paciente
- [x] Intent `receta.ver-recetas-como-paciente`
- [x] Widget PDF web + Flutter
- [ ] Firma PKI / repositorio nacional (Fase 3)
- [ ] Imagen QR embebida en PDF (opcional librería QR)

## Nota firma

`signature_provider = bioenlace-internal` indica emisión en Bioenlace sin certificado AFIP/PKI aún. No sustituye validez legal plena hasta integración oficial.
