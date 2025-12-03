import React from 'react';
import { MessageSquare, Plus, Search } from 'lucide-react';

const Chat = () => {
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Chat</h1>
          <p className="mt-1 text-sm text-gray-500">
            Conversaciones en tiempo real
          </p>
        </div>
        <button className="btn btn-primary">
          <Plus className="h-4 w-4 mr-2" />
          Nueva Conversación
        </button>
      </div>

      {/* Chat Interface */}
      <div className="bg-white shadow rounded-lg h-96">
        <div className="flex h-full">
          {/* Chat List */}
          <div className="w-1/3 border-r border-gray-200">
            <div className="p-4 border-b border-gray-200">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  placeholder="Buscar conversaciones..."
                  className="input pl-10"
                />
              </div>
            </div>
            <div className="p-4">
              {/* Placeholder content */}
              <div className="text-center text-gray-500">
                <MessageSquare className="mx-auto h-8 w-8 text-gray-400" />
                <p className="mt-2 text-sm">No hay conversaciones</p>
              </div>
            </div>
          </div>

          {/* Chat Area */}
          <div className="flex-1 flex items-center justify-center">
            <div className="text-center text-gray-500">
              <MessageSquare className="mx-auto h-12 w-12 text-gray-400" />
              <h3 className="mt-2 text-sm font-medium text-gray-900">
                Selecciona una conversación
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                Elige una conversación del panel izquierdo para comenzar a chatear.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Chat;
