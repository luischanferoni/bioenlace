import React, { useState, useRef, useEffect } from 'react';
import { useChat } from '../../hooks/useChat';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import './ChatComponent.css';

const ChatComponent = ({ consultaId, userId, userRole }) => {
    const [newMessage, setNewMessage] = useState('');
    const messagesEndRef = useRef(null);
    
    const {
        messages,
        isConnected,
        isLoading,
        error,
        sendMessage
    } = useChat(consultaId, userRole);

    // Scroll al final de los mensajes
    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    // Enviar mensaje
    const handleSendMessage = async (e) => {
        e.preventDefault();
        if (!newMessage.trim()) return;

        const success = await sendMessage(newMessage.trim());
        if (success) {
            setNewMessage('');
            setTimeout(scrollToBottom, 100);
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
        return format(date, 'HH:mm', { locale: es });
    };

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

export default ChatComponent;