import React, { useState } from 'react';

const TestComponent = () => {
  const [message, setMessage] = useState('Componente de prueba funcionando');

  const handleClick = () => {
    setMessage('¡Botón clickeado!');
    console.log('Botón clickeado');
  };

  return (
    <div style={{ padding: '20px', border: '1px solid #ccc', margin: '20px' }}>
      <h3>🧪 Componente de Prueba</h3>
      <p>{message}</p>
      <button onClick={handleClick} className="btn btn-primary">
        Probar Click
      </button>
      <div style={{ marginTop: '10px' }}>
        <p><strong>Variables de entorno:</strong></p>
        <ul>
          <li>API Base URL: {import.meta.env.VITE_API_BASE_URL}</li>
          <li>App Name: {import.meta.env.VITE_APP_NAME}</li>
          <li>App Version: {import.meta.env.VITE_APP_VERSION}</li>
        </ul>
      </div>
    </div>
  );
};

export default TestComponent;
