import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { usePersonas } from '../hooks/usePersonas';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

const Personas = () => {
  const { user } = useAuth();
  const [filters, setFilters] = useState({
    search: ''
  });
  
  const {
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
  } = usePersonas(filters);

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSearch = () => {
    loadPersonas(1);
  };

  const handlePageChange = (page) => {
    loadPersonas(page);
  };

  const formatDate = (dateString) => {
    return format(new Date(dateString), 'dd/MM/yyyy', { locale: es });
  };

  const calculateAge = (fechaNacimiento) => {
    if (!fechaNacimiento) return 'N/A';
    const today = new Date();
    const birthDate = new Date(fechaNacimiento);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return age;
  };

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="d-flex justify-content-between align-items-center mb-4">
            <h2>Personas</h2>
            <button className="btn btn-primary">
              <i className="bi bi-plus-circle"></i> Nueva Persona
            </button>
          </div>
          
          {/* Filtros */}
          <div className="card mb-4">
            <div className="card-body">
              <div className="row g-3">
                <div className="col-md-8">
                  <label className="form-label">Buscar</label>
                  <input 
                    type="text" 
                    className="form-control" 
                    name="search"
                    value={filters.search}
                    onChange={handleFilterChange}
                    placeholder="Buscar por nombre, apellido o documento..."
                  />
                </div>
                <div className="col-md-4 d-flex align-items-end">
                  <button className="btn btn-outline-primary" onClick={handleSearch}>
                    <i className="bi bi-search"></i> Buscar
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Lista de personas */}
          <div className="card">
            <div className="card-body">
              {isLoading ? (
                <div className="text-center py-4">
                  <div className="spinner-border" role="status">
                    <span className="visually-hidden">Cargando...</span>
                  </div>
                  <p className="mt-2">Cargando personas...</p>
                </div>
              ) : error ? (
                <div className="alert alert-danger">
                  <i className="bi bi-exclamation-triangle"></i>
                  {error}
                </div>
              ) : personas.length === 0 ? (
                <div className="text-center py-4">
                  <i className="bi bi-people display-1 text-muted"></i>
                  <p className="mt-2">No hay personas</p>
                </div>
              ) : (
                <>
                  <div className="table-responsive">
                    <table className="table table-hover">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Nombre</th>
                          <th>Documento</th>
                          <th>Edad</th>
                          <th>Teléfono</th>
                          <th>Email</th>
                          <th>Fecha Registro</th>
                          <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        {personas.map((persona) => (
                          <tr key={persona.id}>
                            <td>#{persona.id}</td>
                            <td>
                              <div className="d-flex align-items-center">
                                <div className="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                  {persona.nombre.charAt(0).toUpperCase()}
                                </div>
                                {persona.nombre}
                              </div>
                            </td>
                            <td>{persona.documento}</td>
                            <td>{persona.edad} años</td>
                            <td>{persona.telefono || 'N/A'}</td>
                            <td>{persona.email || 'N/A'}</td>
                            <td>{formatDate(persona.created_at)}</td>
                            <td>
                              <div className="btn-group btn-group-sm">
                                <button className="btn btn-outline-primary" title="Ver detalles">
                                  <i className="bi bi-eye"></i>
                                </button>
                                <button className="btn btn-outline-info" title="Ver timeline">
                                  <i className="bi bi-clock-history"></i>
                                </button>
                                <button className="btn btn-outline-success" title="Nueva consulta">
                                  <i className="bi bi-plus-circle"></i>
                                </button>
                                <button className="btn btn-outline-warning" title="Editar">
                                  <i className="bi bi-pencil"></i>
                                </button>
                                <button className="btn btn-outline-danger" title="Eliminar">
                                  <i className="bi bi-trash"></i>
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Paginación */}
                  {pagination.pages > 1 && (
                    <nav className="mt-4">
                      <ul className="pagination justify-content-center">
                        <li className={`page-item ${pagination.page === 1 ? 'disabled' : ''}`}>
                          <button 
                            className="page-link" 
                            onClick={() => handlePageChange(pagination.page - 1)}
                            disabled={pagination.page === 1}
                          >
                            Anterior
                          </button>
                        </li>
                        
                        {Array.from({ length: pagination.pages }, (_, i) => i + 1).map(page => (
                          <li key={page} className={`page-item ${page === pagination.page ? 'active' : ''}`}>
                            <button 
                              className="page-link" 
                              onClick={() => handlePageChange(page)}
                            >
                              {page}
                            </button>
                          </li>
                        ))}
                        
                        <li className={`page-item ${pagination.page === pagination.pages ? 'disabled' : ''}`}>
                          <button 
                            className="page-link" 
                            onClick={() => handlePageChange(pagination.page + 1)}
                            disabled={pagination.page === pagination.pages}
                          >
                            Siguiente
                          </button>
                        </li>
                      </ul>
                    </nav>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Personas;