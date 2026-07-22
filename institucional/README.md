# Página Web Institucional - Bioenlace

Sitio institucional estático para presentar Bioenlace: asistente conversacional, IA integrada y gestión para staff y pacientes.

## Estructura

```
institucional/
├── index.html
├── alta.html              # Wizard self-service clínica / consultorio / ministerio
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
│   ├── signup.js
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
6. **Precios** — Calculador por profesionales × tipo de atención (AMB / EMER / IMP) + opcionales audio / videollamada (COGS + margen por tramo de volumen)
7. **Contacto** — Formulario e información
8. **Alta** (`alta.html`) — Wizard self-service clínica / consultorio (pago simulado) y solicitud ministerio; deep-link `?perfil=consultorio`

## Alta de cuenta (`alta.html` + `js/signup.js`)

Configurar `js/api-config.json` (`apiBaseUrl` → frontend Yii `/api/v1`).  
Tabs: clínica (privado/público; plan AMB/EMER/IMP opcionales, al menos uno), consultorio **solo privado**, **solo ambulatorio × 1 profesional** (unipersonal), ministerio. Tras el alta se muestran `next_steps` (guía para asignarse a un servicio clínico).  
Doc producto: [alta-cuenta-licencia.md](../web/docs/producto/alta-cuenta-licencia.md).

El CTA del calculador (`Crear cuenta` → `alta.html`) guarda la selección en `sessionStorage` (`bioenlace_pricing_selection`) para prellenar el plan del alta.

## Calculador (`js/pricing-core.js` + `js/pricing-calculator.js`)

Núcleo compartido en `pricing-core.js`. En `#precios` se muestra el calculador completo; en `alta.html` solo un **indicador de precio** que se actualiza al cambiar cantidades/opcionales.

**Fórmula:** `precio_unitario = COGS × (1 + margen%/100)`, donde `margen%` sale de `volume_discount_tiers` según la **suma de profesionales** contratados (cualquier tipo). Lista: `margin_on_cost_percent` **233**.  
COGS: base **0,95** ± audio **0,98** (STT profesional ~5 min con **−30 % on-device**) ± videollamada **3,50** (self-host sala/TURN/Track Egress/storage; STT de la llamada = mismo audio, **una sola vez**), columna **con context caching** ([costos-api.md](../web/docs/costos/costos-api.md)).  
El usuario elige clases (AMB / EMER / IMP), cantidad de **profesionales** por clase, y opcionales audio/videollamada. En copy público **no** usar el término PES.

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

Mock del **formulario de encounter en la historia del paciente** (`_formulario_consulta.php`), no del chat del asistente. Pestañas Consulta / Evolución / Guardia: preconsulta o triage → **dictado simulado** (micrófono, ondas, transcripción) → analizar → confirmar. Labels de acción según el flujo. Datos ficticios. La rotación puede pausarse explícitamente.

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
