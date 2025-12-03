# VitaMind React SPA

Aplicación React completa que consume la API de Yii2.

## Estructura del proyecto

```
frontend/web/react/
├── public/
│   ├── index.html
│   └── favicon.ico
├── src/
│   ├── components/
│   │   ├── Layout/
│   │   │   ├── Header.jsx
│   │   │   ├── Sidebar.jsx
│   │   │   └── Layout.jsx
│   │   ├── Chat/
│   │   │   ├── ChatComponent.jsx
│   │   │   └── ChatList.jsx
│   │   ├── Consulta/
│   │   │   ├── ConsultaForm.jsx
│   │   │   ├── ConsultaWizard.jsx
│   │   │   └── ConsultaList.jsx
│   │   ├── Personas/
│   │   │   ├── PersonaProfile.jsx
│   │   │   ├── PersonaTimeline.jsx
│   │   │   └── PersonaList.jsx
│   │   └── Common/
│   │       ├── Modal.jsx
│   │       ├── Loading.jsx
│   │       └── ErrorBoundary.jsx
│   ├── pages/
│   │   ├── Dashboard.jsx
│   │   ├── Consultas.jsx
│   │   ├── Personas.jsx
│   │   ├── Chat.jsx
│   │   └── Profile.jsx
│   ├── hooks/
│   │   ├── useAuth.js
│   │   ├── useApi.js
│   │   └── useWebSocket.js
│   ├── services/
│   │   ├── api.js
│   │   ├── auth.js
│   │   └── websocket.js
│   ├── context/
│   │   ├── AuthContext.jsx
│   │   └── AppContext.jsx
│   ├── utils/
│   │   ├── constants.js
│   │   ├── helpers.js
│   │   └── validators.js
│   ├── styles/
│   │   ├── globals.css
│   │   ├── components.css
│   │   └── pages.css
│   ├── App.jsx
│   └── main.jsx
├── package.json
├── vite.config.js
└── tailwind.config.js
```

## Tecnologías

- **React 18** con hooks
- **React Router** para navegación
- **React Query** para estado del servidor
- **Zustand** para estado global
- **Tailwind CSS** para estilos
- **Socket.io** para chat en tiempo real
- **Axios** para HTTP requests
- **Vite** para build y desarrollo
