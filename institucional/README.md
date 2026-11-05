# Página Web Institucional - Bioenlace

Sitio institucional estático para presentar Bioenlace: asistente conversacional, IA integrada y gestión para staff y pacientes.

## Estructura

```
institucional/
├── index.html
├── precios.html           # Planes: qué incluye + calculadora
├── alta.html              # Crear cuenta desde la app (descarga + contacto ministerio)
├── privacidad.html        # Política de privacidad (apps móviles)
├── css/
│   ├── styles.css
│   └── assistant-demo.css
├── js/
│   ├── main.js
│   ├── assistant-demo.js
│   ├── encounter-demo.js
│   ├── patient-demo.js
│   ├── pricing-core.js
│   ├── pricing-calculator.js
│   └── api-config.json
├── images/
│   ├── logo.svg
│   ├── logo-icon.svg
│   ├── logo-icon.png
│   └── logo-icon-32.png
└── README.md
```

## Secciones

1. **Hero** — Propuesta de valor + CTA **Crear cuenta**
2. **Personal de salud** — Captura clínica web y app
3. **Pacientes** — App paciente y seguimiento
4. **Personal** — Demo del asistente conversacional (listar / editar / crear)
5. **Funcionalidades** — Catálogo por audiencia
6. **Precios** — Teaser en el index; detalle + calculadora en [`precios.html`](precios.html) (qué incluye / no incluye por clase AMB·EMER·IMP)
7. **Contacto** — Formulario e información
8. **Alta** (`alta.html`) — Crear cuenta desde la app Personal de Salud (descarga + verificación de identidad) y contacto para ministerio

## Demo sandbox (`js/demo-access.js`)

CTA **Probar demo como médico** (sección personal de salud) → modal con captcha → `POST /api/v1/licencia/demo-acceso` → redirect a `enter_url` (app `/site/demo-entrar`). Al entrar se provisiona un médico efímero + seed en el efector plantilla. Requiere `demo_sandbox_habilitado` en params del frontend. Captcha: `GET /licencia/demo-captcha`. Doc: [alta-cuenta-licencia.md](../docs/producto/alta-cuenta-licencia.md).

## Alta de cuenta (`alta.html`)

El alta web self-service fue retirada: `alta.html` ahora redirige a descargar la app **Personal de Salud** (donde se verifica la identidad con documento + selfie y se crea la cuenta) y, para ministerios, al contacto. Documentación de producto: [alta-cuenta-licencia.md](../docs/producto/alta-cuenta-licencia.md).

El CTA del calculador (`precios.html`) ya no pre-carga el plan en un formulario de alta; el alta se completa desde la app.

## Calculador (`js/pricing-core.js` + `js/pricing-calculator.js`)

`pricing-core.js` contiene utilidades de selección; no calcula precios. En [`precios.html`](precios.html), el catálogo y las estimaciones se consultan en `GET /licencia/planes` y `POST /licencia/cotizar`. El backend es la única fuente de precios, impuestos y conversión a ARS. `alta.html` deriva el alta a la app.

La página conserva controles para clases (ambulatorio / urgencia / internación), volumen mensual y videollamada opcional. Los chips, el detalle de prestaciones y el CTA se alimentan del catálogo público del backend. La cotización muestra el total en ARS con IVA y el importe base en USD.

Doc comercial: [matriz-argentina-modulos-precios.md](../docs/negocio/modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md).
Catálogo backend: `web/common/components/Domain/Organization/Pes/Domain/Catalog/PricingPesByEncounterClassCatalog.php`.
STT on-device: [stt.md](../docs/negocio/costos/estrategias-reduccion/stt.md).  
Análisis video: [analisis-videollamada-self-host.md](../docs/negocio/costos/analisis-videollamada-self-host.md).  
Roadmap video: [videollamadas.md](../docs/negocio/costos/estrategias-reduccion/videollamadas.md).

## Demo del asistente (`js/assistant-demo.js`)

Mock animado alineado a `spa.css` (burbujas, flow header, tabla, formulario, composer). Tres pestañas con datos ficticios (lorem). Arranca al hacer scroll a la sección; rota automáticamente salvo hover, foco o pausa explícita. Usa `#assistant-demo` para no capturar la demo paciente.

## Demo paciente (`js/patient-demo.js`)

Asistencia, reserva con modalidad clínicamente habilitada, seguimiento de CarePlan (renovación con multiselección → consulta clínica por mensaje) y avisos. Es un mock estático con datos ficticios; no invoca API.

## Demo captura clínica (`js/encounter-demo.js`)

Mock del **formulario de encounter en la historia del paciente** (`_encounter_capture_form.php`), no del chat del asistente. Pestañas Consulta / Evolución / Guardia: preconsulta o triage → **dictado simulado** (micrófono, ondas, transcripción) → analizar → confirmar. Labels de acción según el flujo. Datos ficticios. La rotación puede pausarse explícitamente.

## Desarrollo local

```bash
cd institucional
python -m http.server 8000
```

Abrir `http://localhost:8000`.

## Presentaciones

- [Demo comercial de turnos](../docs/negocio/presentaciones/demo-comercial-turnos.md) — deck Marp centrado en el recorrido integral de turnos.
- [Instrucciones de exportación](../docs/negocio/presentaciones/README.md) — HTML, PDF y PowerPoint.

## Personalización

- **Colores**: variables CSS en `css/styles.css` (`:root`), alineadas al logo (`#093e4d`, `#ff6b6b`, `#38be7f`).
- **Logo**: `images/logo.svg` desde `docs/brand/logo_inkscape.svg`; ícono desde `logo_icono_2.*` con `php web/scripts/generate-favicons.php`.
- **Contenido**: editar textos en `index.html`.
- **Formulario**: envío vía [Web3Forms](https://web3forms.com) a `info@bioenlace.io` (configurar el destino en el panel de Web3Forms con la misma `access_key`). Lógica en `js/main.js`. El `extra` añade o sobrescribe cabeceras cuando aplica.
