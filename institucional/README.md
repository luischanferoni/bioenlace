# Página Web Institucional - Bioenlace

Sitio institucional estático para presentar Bioenlace: asistente conversacional, IA integrada y gestión para staff y pacientes.

## Estructura

```
institucional/
├── index.html
├── privacidad.html   # Política de privacidad (apps móviles)
├── css/styles.css
├── js/main.js
├── images/
│   ├── logo.svg        # Logo horizontal (fuente: web/frontend/web/images/)
│   └── logo-icon.svg   # Ícono / favicon (fuente: web/docs/logo/)
└── README.md
```

## Secciones

1. **Hero** — Propuesta de valor principal
2. **Asistente** — Demo del asistente conversacional (listar / editar / crear)
3. **Personal de salud** — Captura clínica web y app; **Pacientes** — App paciente; **Funcionalidades** — Por audiencia
4. **Contacto** — Formulario e información

## Demo del asistente (`js/assistant-demo.js`)

Mock animado alineado a `spa.css` (burbujas, flow header, tabla, formulario, composer). Tres pestañas con datos ficticios (lorem). Arranca al hacer scroll a la sección; rota automáticamente salvo hover o foco en la demo.

## Demo captura clínica (`js/encounter-demo.js`)

Mock del **formulario de encounter en la historia del paciente** (`_formulario_consulta.php`), no del chat del asistente. Pestañas Consulta / Evolución / Guardia: contexto → **dictado simulado** (micrófono, ondas, transcripción) → analizar → confirmar. Datos ficticios.

## Desarrollo local

```bash
cd institucional
python -m http.server 8000
```

Abrir `http://localhost:8000`.

## Personalización

- **Colores**: variables CSS en `css/styles.css` (`:root`), alineadas al logo (`#093e4d`, `#ff6b6b`, `#38be7f`).
- **Logo**: reemplazar `images/logo.svg` y `images/logo-icon.svg` (mantener sincronía con `web/docs/logo/`).
- **Contenido**: editar textos en `index.html`.
- **Formulario**: envío vía [Web3Forms](https://web3forms.com) a `info@bioenlace.io` (configurar el destino en el panel de Web3Forms con la misma `access_key`). Lógica en `js/main.js`.
