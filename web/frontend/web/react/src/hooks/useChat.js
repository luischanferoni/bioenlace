import { useState, useEffect, useCallback } from 'react';
import { chatService } from '../services/api';
import toast from 'react-hot-toast';

export const useChat = (consultaId, userRole) => {
  const [messages, setMessages] = useState([]);
  const [isConnected, setIsConnected] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState(null);

  // Cargar mensajes
  const loadMessages = useCallback(async () => {
    if (!consultaId) return;

    try {
      setIsLoading(true);
      const response = await chatService.getMessages(consultaId);
      
      if (response.success) {
        setMessages(response.data.messages || []);
        setIsConnected(true);
        setError(null);
      } else {
        setError(response.message);
        setIsConnected(false);
      }
    } catch (err) {
      console.error('Error cargando mensajes:', err);
      setError('Error cargando mensajes');
      setIsConnected(false);
    } finally {
      setIsLoading(false);
    }
  }, [consultaId]);

  // Enviar mensaje
  const sendMessage = useCallback(async (content) => {
    if (!consultaId || !content.trim()) return;

    // Verificar permisos
    if (userRole !== 'medico') {
      toast.error('Solo los médicos pueden usar el chat desde la web');
      return false;
    }

    try {
      const response = await chatService.sendMessage({
        consulta_id: consultaId,
        message: content,
        user_id: localStorage.getItem('user_id'),
        user_role: userRole
      });

      if (response.success) {
        // Recargar mensajes después de enviar
        setTimeout(loadMessages, 500);
        return true;
      } else {
        toast.error(response.message || 'Error enviando mensaje');
        return false;
      }
    } catch (err) {
      console.error('Error enviando mensaje:', err);
      toast.error('Error de conexión');
      return false;
    }
  }, [consultaId, userRole, loadMessages]);

  // Cargar mensajes iniciales
  useEffect(() => {
    loadMessages();
  }, [loadMessages]);

  // Polling para nuevos mensajes
  useEffect(() => {
    if (!consultaId) return;

    const interval = setInterval(loadMessages, 3000);
    return () => clearInterval(interval);
  }, [consultaId, loadMessages]);

  return {
    messages,
    isConnected,
    isLoading,
    error,
    sendMessage,
    loadMessages
  };
};
