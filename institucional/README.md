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
│   ├── pricing-config.json   # Mantener alineado con pricing-pes-by-encounter-class.yaml
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
8. **Alta** (`alta.html`) — Crear cuenta desde la app Personal de Salud (descarga + verificación con Didit) y contacto para ministerio

## Demo sandbox (`js/demo-access.js`)

CTA **Probar demo como médico** (sección personal de salud) → modal con captcha → `POST /api/v1/licencia/demo-acceso` → redirect a `enter_url` (app `/site/demo-entrar`). Al entrar se provisiona un médico efímero + seed en el efector plantilla. Requiere `demo_sandbox_habilitado` en params del frontend. Captcha: `GET /licencia/demo-captcha`. Doc: [alta-cuenta-licencia.md](../web/docs/producto/alta-cuenta-licencia.md).

## Alta de cuenta (`alta.html`)

El alta web self-service fue retirada: `alta.html` ahora redirige a descargar la app **Personal de Salud** (donde se verifica la identidad con Didit y se crea la cuenta) y, para ministerios, al contacto. El plan de trabajo está en [onboarding-mobile-didit](../web/docs/plans/onboarding-mobile-didit/README.md).

El CTA del calculador (`precios.html`) ya no pre-carga el plan en un formulario de alta; el alta se completa desde la app.

## Calculador (`js/pricing-core.js` + `js/pricing-calculator.js`)

Núcleo compartido en `pricing-core.js`. En [`precios.html`](precios.html) está el calculador y, debajo, el listado único de **qué incluye** (`included_features` en el JSON). En `alta.html` solo un **indicador de precio**. El index enlaza al teaser → `precios.html`. Los chips de volumen muestran solo atenciones/mes (sin labels de “1 profesional”, etc.).

**Fórmula:** `precio = atenciones_mes × COGS_por_atención × (1 + margen%/100)`, donde `margen%` sale de `volume_discount_tiers` según la **suma de atenciones** contratadas. Lista: `margin_on_cost_percent` **233**.  
COGS por atención: chat paciente AMB **0,0019** (10 msgs) + motivos audio **0,0014** + captura IA **0,0006** ± dictado **0,0025** ± videollamada **0,0044** (@ **40 %** teleconsulta) ([costos-api.md](../web/docs/costos/costos-api.md)).  
El usuario elige clases (ambulatorio / urgencia / internación), **volumen mensual** de atenciones y opcionales audio/videollamada.

Fuente de cifras del calculador: `js/pricing-config.json` (mantener alineado con `pricing-pes-by-encounter-class.yaml`).
CTA del simulador: `Crear cuenta` → `alta.html` (misma fuente en YAML y JSON).

Doc comercial: [matriz-argentina-modulos-precios.md](../web/docs/modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md).  
STT on-device: [stt.md](../web/docs/costos/estrategias-reduccion/stt.md).  
Análisis video: [analisis-videollamada-self-host.md](../web/docs/costos/analisis-videollamada-self-host.md).  
Roadmap video: [videollamadas.md](../web/docs/costos/estrategias-reduccion/videollamadas.md).

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

- [Demo comercial de turnos](../web/docs/presentaciones/demo-comercial-turnos.md) — deck Marp centrado en el recorrido integral de turnos.
- [Instrucciones de exportación](../web/docs/presentaciones/README.md) — HTML, PDF y PowerPoint.

## Personalización

- **Colores**: variables CSS en `css/styles.css` (`:root`), alineadas al logo (`#093e4d`, `#ff6b6b`, `#38be7f`).
- **Logo**: `images/logo.svg` desde `web/docs/logo/logo_inkscape.svg`; ícono desde `logo_icono_2.*` con `php web/scripts/generate-favicons.php`.
- **Contenido**: editar textos en `index.html`.
- **Formulario**: envío vía [Web3Forms](https://web3forms.com) a `info@bioenlace.io` (configurar el destino en el panel de Web3Forms con la misma `access_key`). Lógica en `js/main.js`. El `extra` añade o sobrescribe cabeceras cuando aplica.
