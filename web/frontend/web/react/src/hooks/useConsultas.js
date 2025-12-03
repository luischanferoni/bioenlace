import { useState, useEffect, useCallback } from 'react';
import { consultaService } from '../services/api';
import toast from 'react-hot-toast';

export const useConsultas = (filters = {}) => {
  const [consultas, setConsultas] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);
  const [pagination, setPagination] = useState({
    page: 1,
    per_page: 20,
    total: 0,
    pages: 0
  });

  // Cargar consultas
  const loadConsultas = useCallback(async (page = 1) => {
    try {
      setIsLoading(true);
      const params = {
        page,
        per_page: pagination.per_page,
        ...filters
      };

      const response = await consultaService.getConsultas(params);
      
      if (response.success) {
        setConsultas(response.data.consultas || []);
        setPagination(response.data.pagination || pagination);
        setError(null);
      } else {
        setError(response.message);
        toast.error(response.message || 'Error cargando consultas');
      }
    } catch (err) {
      console.error('Error cargando consultas:', err);
      setError('Error de conexión');
      toast.error('Error cargando consultas');
    } finally {
      setIsLoading(false);
    }
  }, [filters, pagination.per_page]);

  // Crear consulta
  const createConsulta = useCallback(async (data) => {
    try {
      const response = await consultaService.createConsulta(data);
      
      if (response.success) {
        toast.success('Consulta creada exitosamente');
        loadConsultas(pagination.page); // Recargar lista
        return response.data;
      } else {
        toast.error(response.message || 'Error creando consulta');
        return null;
      }
    } catch (err) {
      console.error('Error creando consulta:', err);
      toast.error('Error de conexión');
      return null;
    }
  }, [loadConsultas, pagination.page]);

  // Actualizar consulta
  const updateConsulta = useCallback(async (id, data) => {
    try {
      const response = await consultaService.updateConsulta(id, data);
      
      if (response.success) {
        toast.success('Consulta actualizada exitosamente');
        loadConsultas(pagination.page); // Recargar lista
        return response.data;
      } else {
        toast.error(response.message || 'Error actualizando consulta');
        return null;
      }
    } catch (err) {
      console.error('Error actualizando consulta:', err);
      toast.error('Error de conexión');
      return null;
    }
  }, [loadConsultas, pagination.page]);

  // Eliminar consulta
  const deleteConsulta = useCallback(async (id) => {
    try {
      const response = await consultaService.deleteConsulta(id);
      
      if (response.success) {
        toast.success('Consulta eliminada exitosamente');
        loadConsultas(pagination.page); // Recargar lista
        return true;
      } else {
        toast.error(response.message || 'Error eliminando consulta');
        return false;
      }
    } catch (err) {
      console.error('Error eliminando consulta:', err);
      toast.error('Error de conexión');
      return false;
    }
  }, [loadConsultas, pagination.page]);

  // Cargar consultas iniciales
  useEffect(() => {
    loadConsultas();
  }, [loadConsultas]);

  return {
    consultas,
    isLoading,
    error,
    pagination,
    loadConsultas,
    createConsulta,
    updateConsulta,
    deleteConsulta
  };
};
