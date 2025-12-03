import React from 'react';
import { User, Mail, Phone, MapPin, Calendar } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const Profile = () => {
  const { user } = useAuth();

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Mi Perfil</h1>
        <p className="mt-1 text-sm text-gray-500">
          Gestiona tu información personal y configuración
        </p>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Profile Info */}
        <div className="lg:col-span-2">
          <div className="bg-white shadow rounded-lg">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-medium text-gray-900">Información Personal</h3>
            </div>
            <div className="p-6">
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                  <label className="block text-sm font-medium text-gray-700">
                    Nombre
                  </label>
                  <input
                    type="text"
                    defaultValue={user?.name || ''}
                    className="mt-1 input"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">
                    Email
                  </label>
                  <input
                    type="email"
                    defaultValue={user?.email || ''}
                    className="mt-1 input"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">
                    Teléfono
                  </label>
                  <input
                    type="tel"
                    className="mt-1 input"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700">
                    Rol
                  </label>
                  <input
                    type="text"
                    value={user?.role === 'medico' ? 'Médico' : 
                           user?.role === 'enfermeria' ? 'Enfermería' : 'Usuario'}
                    disabled
                    className="mt-1 input bg-gray-50"
                  />
                </div>
              </div>
              <div className="mt-6">
                <button className="btn btn-primary">
                  Guardar Cambios
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Profile Card */}
        <div>
          <div className="bg-white shadow rounded-lg">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-medium text-gray-900">Perfil</h3>
            </div>
            <div className="p-6 text-center">
              <div className="mx-auto h-20 w-20 bg-blue-500 rounded-full flex items-center justify-center">
                <User className="h-10 w-10 text-white" />
              </div>
              <h4 className="mt-4 text-lg font-medium text-gray-900">
                {user?.name || 'Usuario'}
              </h4>
              <p className="mt-1 text-sm text-gray-500">
                {user?.role === 'medico' ? 'Médico' : 
                 user?.role === 'enfermeria' ? 'Enfermería' : 'Usuario'}
              </p>
              <div className="mt-4 space-y-2 text-sm text-gray-500">
                <div className="flex items-center justify-center">
                  <Mail className="h-4 w-4 mr-2" />
                  {user?.email || 'email@ejemplo.com'}
                </div>
                <div className="flex items-center justify-center">
                  <Calendar className="h-4 w-4 mr-2" />
                  Miembro desde 2024
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Profile;
