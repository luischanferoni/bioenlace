# Fase 02 — Página web, QR y código

## Objetivo

Que la computadora muestre el QR, y que al abrir el teléfono la URL aparezca el código, sin Didit todavía (el vínculo puede detenerse en `claimed`).

## Tareas

- [x] Extraer el render de QR de `common/controllers/WebAuthController.php` (`renderQrCodeSvg`) a `common/components/Shared/Infrastructure/Qr/QrSvgRenderer.php` y reusarlo en el login, sin cambiar el contrato de `/auth/qr-login-start`.
- [x] Crear `web/frontend/controllers/AltaController.php` (id `alta`) con acciones JSON: `vinculo-iniciar`, `vinculo-estado`, `vinculo-cancelar`. Formato JSON y `X-CSRF-Token` como el resto de las acciones del login.
- [x] `actionIndex()`: arma un view model con textos, URLs de las acciones, catálogo de centros y flag de habilitación; registra el `AssetBundle`; renderiza `frontend/views/alta/index.php` con `loginLayout`.
- [x] Vista delgada: paso "elegí tu centro" (buscador y filtros sector/provincia contra `GET /api/v1/licencia/catalogo-centros`), paso "escaneá el QR", paso "ingresá este código en tu teléfono", paso "esperando la verificación", paso resultado. Sin lógica de request en la vista.
- [ ] Añadir `frontend/web/js/alta-vinculo.js` + `frontend/assets/AltaVinculoAsset.php`: iniciar vínculo, pintar el SVG del QR, revelar el código cuando el estado es `claimed`, consultar estado cada 2 s, cortar al expirar y permitir reiniciar conservando el centro.
- [x] Reglas de `urlManager`: `alta` → `alta/index`, `POST alta/vinculo-iniciar`, `POST alta/vinculo-estado`, `POST alta/vinculo-cancelar`, antes de los patrones amplios.
- [ ] El centro elegido se envía al iniciar y el servidor lo revalida con `SignUpInstitutionalEfector::findAvailableCenter`; el navegador no puede fijarlo por query string después.
- [x] Guard de abuso en `vinculo-iniciar` (honeypot + límite por IP del alta).
- [x] Knob `webSignupPairing.enabled`: con el alta deshabilitada, `/alta` muestra un aviso y no emite QR.
- [x] Enlaces de entrada: CTA en `frontend/views/login/login.php` ("Crear cuenta") y `institucional/alta.html` apuntando al alta web (actualizar su README).
- [x] Accesibilidad y mobile-first con Bootstrap: foco en el código, texto alternativo del QR, mensajes de error con `role="status"`.

## Criterio de salida

Abrir `/alta` genera un vínculo; el QR se ve nítido en pantalla y en un teléfono real; abrir la URL del QR en el teléfono hace que la computadora muestre el código; recargar la computadora no invalida el vínculo en curso; el vínculo expira solo y se puede reiniciar sin recargar la página.

## Estado

Implementado:

- `common/components/Shared/Infrastructure/Qr/QrSvgRenderer` (extra?do de `WebAuthController`, que ahora lo reusa).
- `frontend/controllers/AltaController` con `index`, `vinculo-iniciar`, `vinculo-estado`, `vinculo-cancelar`.
- `frontend/views/alta/index.php` (pasos + plantilla de resultados), `frontend/assets/AltaVinculoAsset`, `frontend/web/js/alta-vinculo.js`.
- Rutas `alta`, `alta/vinculo-iniciar`, `alta/vinculo-estado`, `alta/vinculo-cancelar` en `frontend/config/main.php` (verificadas con `parseRequest`).
- CTA "Crear cuenta" en el login y enlace desde `institucional/alta.html`.

Interino conocido: el QR apunta a `/alta/vinculo/<token>`, que **se implementa en la fase 03**. Hasta entonces, escanearlo no tiene p?gina destino. No se despliega nada en este estado.

Verificado: `php -l` de los archivos, `node --check` del JS, render del QR (SVG 45?45, `aria-label` correcto) y las rutas resueltas contra la config real. No se pudo probar en navegador ni con base: esta m?quina no tiene MySQL y el QR exige el v?nculo guardado.
