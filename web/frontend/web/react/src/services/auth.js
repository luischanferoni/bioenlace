import api from './api';

export const authService = {
  // Login
  login: async (credentials) => {
    try {
      const response = await api.post('/auth/login', credentials);
      
      if (response.data.success) {
        // Guardar token en localStorage
        localStorage.setItem('auth_token', response.data.data.token);
        
        return {
          user: response.data.data.user,
          token: response.data.data.token,
          success: true
        };
      } else {
        throw new Error(response.data.message || 'Error en el login');
      }
    } catch (error) {
      throw new Error(error.response?.data?.message || 'Error de conexión');
    }
  },

  // Logout
  logout: async () => {
    try {
      await api.post('/auth/logout');
    } catch (error) {
      console.error('Error en logout:', error);
    } finally {
      localStorage.removeItem('auth_token');
    }
  },

  // Obtener usuario actual
  getCurrentUser: async () => {
    try {
      const response = await api.get('/auth/me');
      return response.data.user;
    } catch (error) {
      throw new Error('Error obteniendo usuario actual');
    }
  },

  // Verificar si está autenticado
  isAuthenticated: () => {
    return !!localStorage.getItem('auth_token');
  },

  // Obtener token
  getToken: () => {
    return localStorage.getItem('auth_token');
  },

  // Refrescar token
  refreshToken: async () => {
    try {
      const response = await api.post('/auth/refresh');
      const newToken = response.data.token;
      localStorage.setItem('auth_token', newToken);
      return newToken;
    } catch (error) {
      localStorage.removeItem('auth_token');
      throw new Error('Error refrescando token');
    }
  }
};
