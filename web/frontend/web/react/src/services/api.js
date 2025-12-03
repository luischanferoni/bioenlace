import axios from 'axios';

// Configuración base de la API
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8080/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor para agregar token de autenticación
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor para manejar respuestas
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      // Token expirado o inválido
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Servicios específicos
export const chatService = {
  getMessages: async (consultaId) => {
    const response = await api.get(`/chat/messages/${consultaId}`);
    return response.data;
  },
  
  sendMessage: async (data) => {
    const response = await api.post('/chat/send', data);
    return response.data;
  },
  
  getStatus: async (consultaId) => {
    const response = await api.get(`/chat/status/${consultaId}`);
    return response.data;
  }
};

export const consultaService = {
  getConsultas: async (params = {}) => {
    const response = await api.get('/consultas', { params });
    return response.data;
  },
  
  getConsulta: async (id) => {
    const response = await api.get(`/consultas/${id}`);
    return response.data;
  },
  
  createConsulta: async (data) => {
    const response = await api.post('/consultas', data);
    return response.data;
  },
  
  updateConsulta: async (id, data) => {
    const response = await api.put(`/consultas/${id}`, data);
    return response.data;
  },
  
  deleteConsulta: async (id) => {
    const response = await api.delete(`/consultas/${id}`);
    return response.data;
  }
};

export const personaService = {
  getPersonas: async (params = {}) => {
    const response = await api.get('/personas', { params });
    return response.data;
  },
  
  getPersona: async (id) => {
    const response = await api.get(`/personas/${id}`);
    return response.data;
  },
  
  getPersonaTimeline: async (id) => {
    const response = await api.get(`/personas/${id}/timeline`);
    return response.data;
  },
  
  createPersona: async (data) => {
    const response = await api.post('/personas', data);
    return response.data;
  },
  
  updatePersona: async (id, data) => {
    const response = await api.put(`/personas/${id}`, data);
    return response.data;
  },
  
  deletePersona: async (id) => {
    const response = await api.delete(`/personas/${id}`);
    return response.data;
  }
};

export default api;