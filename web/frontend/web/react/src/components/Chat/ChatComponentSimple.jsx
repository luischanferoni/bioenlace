import React, { useState, useEffect, useRef } from 'react';

const ChatComponentSimple = ({ consultaId, userId, userRole }) => {
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const [isConnected, setIsConnected] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const messagesEndRef = useRef(null);

    // URL base de la API
    const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vitamind/VitaMind/api/v1';

    // Scroll al final de los mensajes
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    // Cargar mensajes
    const loadMessages = async () => {
        if (!consultaId) return;

        try {
            setIsLoading(true);
            console.log('Cargando mensajes para consulta:', consultaId);
            console.log('URL de la API:', `${API_BASE_URL}/consulta-chat/messages/${consultaId}`);
            
            const response = await fetch(`${API_BASE_URL}/consulta-chat/messages/${consultaId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                const data = await response.json();
                console.log('Respuesta de la API:', data);
                
                if (data.success) {
                    setMessages(data.data.messages || []);
                    setIsConnected(true);
                    setError(null);
                } else {
                    setError(data.message || 'Error cargando mensajes');
                    setIsConnected(false);
                }
            } else {
                console.error('Error HTTP:', response.status);
                setError('Error de conexión con el servidor');
                setIsConnected(false);
            }
        } catch (err) {
            console.error('Error cargando mensajes:', err);
            setError('Error cargando mensajes');
            setIsConnected(false);
        } finally {
            setIsLoading(false);
        }
    };

    // Enviar mensaje
    const handleSendMessage = async (e) => {
        e.preventDefault();
        if (!newMessage.trim()) return;

        // Verificar permisos
        if (userRole !== 'medico') {
            alert('Solo los médicos pueden usar el chat desde la web');
            return;
        }

        const messageText = newMessage.trim();
        setNewMessage('');

        try {
            console.log('Enviando mensaje:', messageText);
            console.log('URL de envío:', `${API_BASE_URL}/consulta-chat/send`);
            
            const response = await fetch(`${API_BASE_URL}/consulta-chat/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    consulta_id: consultaId,
                    message: messageText,
                    user_id: userId,
                    user_role: userRole
                })
            });

            if (response.ok) {
                const data = await response.json();
                console.log('Respuesta del envío:', data);
                
                if (data.success) {
                    // Recargar mensajes después de enviar
                    setTimeout(loadMessages, 500);
                } else {
                    alert('Error enviando mensaje: ' + data.message);
                }
            } else {
                console.error('Error HTTP:', response.status);
                alert('Error de conexión con el servidor');
            }
        } catch (err) {
            console.error('Error enviando mensaje:', err);
            alert('Error de conexión');
        }
    };

    // Manejar tecla Enter
    const handleKeyPress = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage(e);
        }
    };

    // Formatear tiempo
    const formatTime = (timestamp) => {
        const date = new Date(timestamp);
        return date.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    };

    // Cargar mensajes iniciales
    useEffect(() => {
        loadMessages();
    }, [consultaId]);

    // Scroll automático cuando hay nuevos mensajes
    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    if (isLoading) {
        return (
            <div className="chat-container">
                <div className="chat-loading">
                    <div className="spinner-border spinner-border-sm" role="status">
                        <span className="visually-hidden">Cargando mensajes...</span>
                    </div>
                    Cargando mensajes...
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="chat-container">
                <div className="chat-error">
                    <i className="bi bi-exclamation-triangle"></i>
                    <p>Error: {error}</p>
                </div>
            </div>
        );
    }

    return (
        <div className="chat-container">
            <div className="chat-header">
                <h5>Chat de Consulta #{consultaId}</h5>
                <div className="chat-status">
                    <span className={`status-indicator ${isConnected ? 'connected' : 'disconnected'}`}></span>
                    <span className="status-text">{isConnected ? 'Conectado' : 'Desconectado'}</span>
                </div>
            </div>
            
            {/* Debug info */}
            <div style={{ padding: '10px', backgroundColor: '#f0f0f0', fontSize: '12px', borderBottom: '1px solid #ddd' }}>
                <strong>Debug Info:</strong><br/>
                API Base URL: {API_BASE_URL}<br/>
                Consulta ID: {consultaId}<br/>
                User ID: {userId}<br/>
                User Role: {userRole}
            </div>
            
            <div className="chat-messages">
                {messages.length === 0 ? (
                    <div className="no-messages">
                        <i className="bi bi-chat-dots"></i>
                        <p>No hay mensajes aún</p>
                    </div>
                ) : (
                    messages.map((message) => (
                        <div key={message.id} className={`message ${message.user_role}`}>
                            <div className="message-content">
                                {message.content}
                                <div className="message-info">
                                    {formatTime(message.created_at)}
                                    {message.user_role === 'medico' 
                                        ? ` - Dr. ${message.user_name}` 
                                        : ` - ${message.user_name}`
                                    }
                                </div>
                            </div>
                        </div>
                    ))
                )}
                <div ref={messagesEndRef} />
            </div>
            
            <div className="chat-input">
                <form onSubmit={handleSendMessage}>
                    <div className="input-group">
                        <input
                            type="text"
                            className="form-control"
                            value={newMessage}
                            onChange={(e) => setNewMessage(e.target.value)}
                            onKeyPress={handleKeyPress}
                            placeholder="Escribe tu mensaje..."
                            maxLength="500"
                            disabled={userRole !== 'medico'}
                        />
                        <button 
                            type="submit"
                            className="btn btn-primary" 
                            disabled={userRole !== 'medico' || !newMessage.trim()}
                        >
                            <i className="bi bi-send"></i> Enviar
                        </button>
                    </div>
                </form>
                <div className="input-footer">
                    <small className="text-muted">
                        {userRole === 'medico' 
                            ? 'Puedes enviar mensajes desde la web'
                            : 'Solo los médicos pueden usar el chat desde la web'
                        }
                    </small>
                </div>
            </div>
        </div>
    );
};

export default ChatComponentSimple;
