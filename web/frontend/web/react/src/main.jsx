import React from 'react';
import ReactDOM from 'react-dom/client';
import AppSimple from './AppSimple';
import './styles/basic.css';

// Crear root y renderizar la aplicación
const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <AppSimple />
  </React.StrictMode>
);
