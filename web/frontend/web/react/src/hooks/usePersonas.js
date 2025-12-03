import { useState, useEffect, useCallback } from 'react';
import { personaService } from '../services/api';
import toast from 'react-hot-toast';

export const usePersonas = (filters = {}) => {
  const [personas, setPersonas] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState({
    page: 1,
    per_page: 20,
    total: 0,
    pages: 0
  });

  // Cargar personas
  const loadPersonas = useCallback(async (page = 1) => {
    try {
      setIsLoading(true);
      const params = {
        page,
        per_page: pagination.per_page,
        ...filters
      };

      const response = await personaService.getPersonas(params);
      
      if (response.success) {
        setPersonas(response.data.personas || []);
        setPagination(response.data.pagination || pagination);
        setError(null);
      } else {
        setError(response.message);
        toast.error(response.message || 'Error cargando personas');
      }
    } catch (err) {
      console.error('Error cargando personas:', err);
      setError('Error de conexión');
      toast.error('Error cargando personas');
    } finally {
      setIsLoading(false);
    }
  }, [filters, pagination.per_page]);

  // Obtener persona por ID
  const getPersona = useCallback(async (id) => {
    try {
      const response = await personaService.getPersona(id);
      
      if (response.success) {
        return response.data;
      } else {
        toast.error(response.message || 'Error cargando persona');
        return null;
      }
    } catch (err) {
      console.error('Error cargando persona:', err);
      toast.error('Error de conexión');
      return null;
    }
  }, []);

  // Obtener timeline de persona
  const getPersonaTimeline = useCallback(async (id) => {
    try {
      const response = await personaService.getPersonaTimeline(id);
      
      if (response.success) {
        return response.data;
      } else {
        toast.error(response.message || 'Error cargando timeline');
        return null;
      }
    } catch (err) {
      console.error('Error cargando timeline:', err);
      toast.error('Error de conexión');
      return null;
    }
  }, []);

  // Crear persona
  const createPersona = useCallback(async (data) => {
    try {
      const response = await personaService.createPersona(data);
      
      if (response.success) {
        toast.success('Persona creada exitosamente');
        loadPersonas(pagination.page); // Recargar lista
        return response.data;
      } else {
        toast.error(response.message || 'Error creando persona');
        return null;
      }
    } catch (err) {
      console.error('Error creando persona:', err);
      toast.error('Error de conexión');
      return null;
    }
  }, [loadPersonas, pagination.page]);

  // Actualizar persona
  const updatePersona = useCallback(async (id, data) => {
    try {
      const response = await personaService.updatePersona(id, data);
      
      if (response.success) {
        toast.success('Persona actualizada exitosamente');
        loadPersonas(pagination.page); // Recargar lista
        return response.data;
      } else {
        toast.error(response.message || 'Error actualizando persona');
        return null;
      }
    } catch (err) {
      console.error('Error actualizando persona:', err);
      toast.error('Error de conexión');
      return null;
    }
  }, [loadPersonas, pagination.page]);

  // Eliminar persona
  const deletePersona = useCallback(async (id) => {
    try {
      const response = await personaService.deletePersona(id);
      
      if (response.success) {
        toast.success('Persona eliminada exitosamente');
        loadPersonas(pagination.page); // Recargar lista
        return true;
      } else {
        toast.error(response.message || 'Error eliminando persona');
        return false;
      }
    } catch (err) {
      console.error('Error eliminando persona:', err);
      toast.error('Error de conexión');
      return false;
    }
  }, [loadPersonas, pagination.page]);

  // Cargar personas iniciales
  useEffect(() => {
    loadPersonas();
  }, [loadPersonas]);

  return {
    personas,
    isLoading,
    error,
    pagination,
    loadPersonas,
    getPersona,
    getPersonaTimeline,
    createPersona,
    updatePersona,
    deletePersona
  };
};
