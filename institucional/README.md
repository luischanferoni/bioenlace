# Página Web Institucional - Bioenlace

Sitio institucional estático para presentar Bioenlace: asistente conversacional, IA integrada y gestión para staff y pacientes.

## Estructura

```
institucional/
├── index.html
├── css/styles.css
├── js/main.js
├── images/
│   ├── logo.svg        # Logo horizontal (fuente: web/frontend/web/images/)
│   └── logo-icon.svg   # Ícono / favicon (fuente: web/docs/logo/)
└── README.md
```

## Secciones

1. **Hero** — Propuesta de valor principal
2. **Plataforma** — Introducción y pilares
3. **Para el staff** — Beneficios operativos (CRUD / asistente)
4. **IA integrada** — Cómo funciona la inteligencia artificial
5. **Pacientes** — Turnos, chat IA y alertas (resumen)
6. **Contacto** — Formulario e información

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
- **Formulario**: conectar envío en `js/main.js` (hoy muestra alerta de confirmación).
