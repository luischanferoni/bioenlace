// Script de prueba para la API
const express = require('express');
const cors = require('cors');
const app = express();
const PORT = 8080;

// Middleware
app.use(cors());
app.use(express.json());

// Datos de prueba
const users = [
  { id: 1, name: 'Dr. Juan Pérez', email: 'juan@test.com', role: 'medico' },
  { id: 2, name: 'María García', email: 'maria@test.com', role: 'paciente' }
];

const consultas = [
  { id: 1, paciente: 'María García', fecha: '2024-01-15', motivo: 'Dolor de cabeza', status: 'pendiente', medico: 'Dr. Juan Pérez' },
  { id: 2, paciente: 'Carlos López', fecha: '2024-01-16', motivo: 'Fiebre', status: 'en_proceso', medico: 'Dr. Juan Pérez' }
];

const personas = [
  { id: 1, nombre: 'María García', documento: '12345678', edad: 35, telefono: '123-456-7890', email: 'maria@test.com', created_at: '2024-01-01' },
  { id: 2, nombre: 'Carlos López', documento: '87654321', edad: 42, telefono: '098-765-4321', email: 'carlos@test.com', created_at: '2024-01-02' }
];

const messages = [
  { id: 1, consulta_id: 1, user_id: 1, user_name: 'Dr. Juan Pérez', user_role: 'medico', content: 'Hola, ¿cómo te sientes?', created_at: '2024-01-15 10:00:00' },
  { id: 2, consulta_id: 1, user_id: 2, user_name: 'María García', user_role: 'paciente', content: 'Me duele mucho la cabeza', created_at: '2024-01-15 10:05:00' }
];

// Rutas de autenticación
app.post('/api/auth/login', (req, res) => {
  const { email, password } = req.body;
  
  if (email === 'juan@test.com' && password === 'password') {
    res.json({
      success: true,
      data: {
        user: users[0],
        token: 'fake-jwt-token-123'
      },
      message: 'Login exitoso'
    });
  } else {
    res.status(401).json({
      success: false,
      message: 'Credenciales inválidas'
    });
  }
});

app.get('/api/auth/me', (req, res) => {
  res.json({
    success: true,
    data: users[0]
  });
});

// Rutas de chat
app.get('/api/chat/messages/:id', (req, res) => {
  const consultaId = parseInt(req.params.id);
  const consultaMessages = messages.filter(m => m.consulta_id === consultaId);
  
  res.json({
    success: true,
    data: {
      messages: consultaMessages,
      consulta: { id: consultaId, paciente: 'María García' }
    }
  });
});

app.post('/api/chat/send', (req, res) => {
  const { consulta_id, message, user_id, user_role } = req.body;
  
  const newMessage = {
    id: messages.length + 1,
    consulta_id,
    user_id,
    user_name: user_role === 'medico' ? 'Dr. Juan Pérez' : 'María García',
    user_role,
    content: message,
    created_at: new Date().toISOString()
  };
  
  messages.push(newMessage);
  
  res.json({
    success: true,
    data: newMessage,
    message: 'Mensaje enviado exitosamente'
  });
});

// Rutas de consultas
app.get('/api/consultas', (req, res) => {
  const page = parseInt(req.query.page) || 1;
  const perPage = parseInt(req.query.per_page) || 20;
  
  res.json({
    success: true,
    data: {
      consultas: consultas,
      pagination: {
        page,
        per_page: perPage,
        total: consultas.length,
        pages: Math.ceil(consultas.length / perPage)
      }
    }
  });
});

// Rutas de personas
app.get('/api/personas', (req, res) => {
  const page = parseInt(req.query.page) || 1;
  const perPage = parseInt(req.query.per_page) || 20;
  
  res.json({
    success: true,
    data: {
      personas: personas,
      pagination: {
        page,
        per_page: perPage,
        total: personas.length,
        pages: Math.ceil(personas.length / perPage)
      }
    }
  });
});

// Iniciar servidor
app.listen(PORT, () => {
  console.log(`🚀 Servidor de prueba API ejecutándose en http://localhost:${PORT}`);
  console.log(`📱 Endpoints disponibles:`);
  console.log(`  POST /api/auth/login`);
  console.log(`  GET  /api/auth/me`);
  console.log(`  GET  /api/chat/messages/:id`);
  console.log(`  POST /api/chat/send`);
  console.log(`  GET  /api/consultas`);
  console.log(`  GET  /api/personas`);
});
