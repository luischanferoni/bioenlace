import React from 'react';
import TestComponent from './TestComponent';
import ChatComponentSimple from './components/Chat/ChatComponentSimple';
import './styles/basic.css';

function AppSimple() {
  return (
    <div style={{ padding: '20px', fontFamily: 'Arial, sans-serif' }}>
      <h1>🧪 VitaMind - Prueba de Integración</h1>
      
      <div style={{ marginBottom: '30px' }}>
        <TestComponent />
      </div>

      <div style={{ marginBottom: '30px' }}>
        <h3>💬 Chat de Prueba</h3>
        <ChatComponentSimple 
          consultaId={1}
          userId={1}
          userRole="medico"
        />
      </div>

      <div style={{ marginTop: '30px', padding: '20px', backgroundColor: '#f8f9fa', borderRadius: '8px' }}>
        <h4>📋 Estado de la aplicación:</h4>
        <ul>
          <li>✅ React funcionando</li>
          <li>✅ Componentes cargando</li>
          <li>✅ Variables de entorno configuradas</li>
          <li>🔧 Chat con datos simulados</li>
        </ul>
      </div>
    </div>
  );
}

export default AppSimple;
