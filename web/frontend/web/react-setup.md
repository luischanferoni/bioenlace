# Configuración React para VitaMind

## 1. Instalar dependencias

```bash
# En la raíz del proyecto
npm init -y
npm install react react-dom
npm install --save-dev @vitejs/plugin-react vite
npm install --save-dev @types/react @types/react-dom typescript
```

## 2. Configurar Vite (vite.config.js)

```javascript
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'web/dist',
    rollupOptions: {
      input: {
        main: 'web/react/src/main.jsx',
        chat: 'web/react/src/chat.jsx'
      }
    }
  },
  server: {
    port: 3000,
    proxy: {
      '/api': 'http://localhost:8080' // Tu servidor Yii2
    }
  }
})
```

## 3. Estructura de carpetas

```
frontend/
├── web/
│   ├── react/
│   │   ├── src/
│   │   │   ├── components/
│   │   │   │   ├── Chat/
│   │   │   │   ├── ConsultaForm/
│   │   │   │   └── Timeline/
│   │   │   ├── hooks/
│   │   │   ├── services/
│   │   │   └── utils/
│   │   ├── public/
│   │   └── package.json
│   └── dist/ (generado por Vite)
```

## 4. Scripts de desarrollo

```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview"
  }
}
```
