# Página Web Institucional - Bioenlace

Página web institucional moderna y responsiva para Bioenlace, plataforma de gestión médica.

## 📋 Descripción

Esta es una página web institucional completa que presenta información sobre Bioenlace, sus servicios, características y opciones de contacto. La página está diseñada con un enfoque moderno, profesional y completamente responsivo.

## 🎨 Características

- **Diseño Moderno**: Interfaz limpia y profesional
- **Totalmente Responsivo**: Adaptable a todos los dispositivos (móvil, tablet, desktop)
- **Navegación Suave**: Scroll suave entre secciones
- **Animaciones**: Efectos visuales sutiles y profesionales
- **Formulario de Contacto**: Formulario funcional para recibir consultas
- **Optimizado**: Carga rápida y optimizado para SEO

## 📁 Estructura de Archivos

```
institucional/
├── index.html          # Página principal HTML
├── css/
│   └── styles.css      # Estilos CSS
├── js/
│   └── main.js         # JavaScript para interactividad
└── README.md           # Este archivo
```

## 🚀 Cómo Usar

### Opción 1: Abrir directamente en el navegador

1. Navega a la carpeta `institucional`
2. Abre el archivo `index.html` en tu navegador web preferido

### Opción 2: Servidor local

Si deseas ejecutar la página en un servidor local (recomendado para desarrollo):

#### Con Python:
```bash
cd institucional
python -m http.server 8000
```
Luego abre `http://localhost:8000` en tu navegador.

#### Con Node.js (http-server):
```bash
npm install -g http-server
cd institucional
http-server -p 8000
```

#### Con PHP:
```bash
cd institucional
php -S localhost:8000
```

## 📱 Secciones de la Página

1. **Hero/Inicio**: Sección principal con título y llamados a la acción
2. **Sobre Nosotros**: Información sobre Bioenlace y estadísticas
3. **Servicios**: Descripción de los servicios principales
4. **Características**: Características destacadas de la plataforma
5. **Contacto**: Formulario de contacto e información de contacto
6. **Footer**: Enlaces adicionales y redes sociales

## 🎨 Personalización

### Colores

Los colores principales se pueden modificar en el archivo `css/styles.css` en la sección `:root`:

```css
:root {
    --primary-color: #2563eb;      /* Color principal */
    --secondary-color: #10b981;    /* Color secundario */
    --accent-color: #f59e0b;       /* Color de acento */
    /* ... más variables */
}
```

### Contenido

Para modificar el contenido de la página, edita el archivo `index.html`:

- **Títulos y textos**: Busca las secciones correspondientes y modifica el texto
- **Estadísticas**: Modifica los valores en `data-target` de los elementos `.stat-number`
- **Información de contacto**: Actualiza los datos en la sección de contacto
- **Enlaces sociales**: Modifica los enlaces en el footer

### Imágenes

Actualmente la página usa iconos de Font Awesome. Para agregar imágenes:

1. Crea una carpeta `images/` dentro de `institucional/`
2. Agrega tus imágenes
3. Reemplaza el placeholder de imagen en la sección "Sobre Nosotros":

```html
<div class="about-image">
    <img src="images/tu-imagen.jpg" alt="Descripción">
</div>
```

## 🔧 Funcionalidades JavaScript

- **Menú móvil**: Navegación hamburguesa para dispositivos móviles
- **Scroll suave**: Navegación suave entre secciones
- **Animaciones**: Efectos de aparición al hacer scroll
- **Contadores animados**: Estadísticas que se animan al ser visibles
- **Formulario**: Manejo del formulario de contacto (actualmente muestra alerta, se puede conectar a backend)

## 📝 Notas

- El formulario de contacto actualmente muestra una alerta al enviarse. Para conectarlo a un backend, modifica la función de envío en `js/main.js`
- Las estadísticas son ejemplos y deben ser actualizadas con datos reales
- Los enlaces de redes sociales en el footer están como placeholders (#) y deben ser actualizados con URLs reales
- La información de contacto (email, teléfono, dirección) debe ser actualizada con datos reales

## 🌐 Compatibilidad

La página es compatible con:
- Chrome (últimas versiones)
- Firefox (últimas versiones)
- Safari (últimas versiones)
- Edge (últimas versiones)
- Navegadores móviles (iOS Safari, Chrome Mobile)

## 📄 Licencia

Este proyecto es parte de Bioenlace.

## 👥 Soporte

Para preguntas o soporte, contacta al equipo de desarrollo de Bioenlace.

