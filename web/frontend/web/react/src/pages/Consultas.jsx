import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useConsultas } from '../hooks/useConsultas';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

const Consultas = () => {
  const { user } = useAuth();
  const [filters, setFilters] = useState({
    status: '',
    fecha_desde: '',
    fecha_hasta: ''
  });
  
  const {
    consultas,
    isLoading,
    error,
    pagination,
    loadConsultas,
    createConsulta,
    updateConsulta,
    deleteConsulta
  } = useConsultas(filters);

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    setFilters(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSearch = () => {
    loadConsultas(1);
  };

  const handlePageChange = (page) => {
    loadConsultas(page);
  };

  const formatDate = (dateString) => {
    return format(new Date(dateString), 'dd/MM/yyyy HH:mm', { locale: es });
  };

  const getStatusBadge = (status) => {
    const statusClasses = {
      'pendiente': 'bg-warning',
      'en_proceso': 'bg-info',
      'completada': 'bg-success',
      'cancelada': 'bg-danger'
    };
    
    return (
      <span className={`badge ${statusClasses[status] || 'bg-secondary'}`}>
        {status.replace('_', ' ').toUpperCase()}
      </span>
    );
  };

  return (
    <div className="container-fluid">
      <div className="row">
        <div className="col-12">
          <div className="d-flex justify-content-between align-items-center mb-4">
            <h2>Consultas</h2>
            <button className="btn btn-primary">
              <i className="bi bi-plus-circle"></i> Nueva Consulta
            </button>
          </div>
          
          {/* Filtros */}
          <div className="card mb-4">
            <div className="card-body">
              <div className="row g-3">
                <div className="col-md-3">
                  <label className="form-label">Estado</label>
                  <select 
                    className="form-select" 
                    name="status" 
                    value={filters.status}
                    onChange={handleFilterChange}
                  >
                    <option value="">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="completada">Completada</option>
                    <option value="cancelada">Cancelada</option>
                  </select>
                </div>
                <div className="col-md-3">
                  <label className="form-label">Fecha Desde</label>
                  <input 
                    type="date" 
                    className="form-control" 
                    name="fecha_desde"
                    value={filters.fecha_desde}
                    onChange={handleFilterChange}
                  />
                </div>
                <div className="col-md-3">
                  <label className="form-label">Fecha Hasta</label>
                  <input 
                    type="date" 
                    className="form-control" 
                    name="fecha_hasta"
                    value={filters.fecha_hasta}
                    onChange={handleFilterChange}
                  />
                </div>
                <div className="col-md-3 d-flex align-items-end">
                  <button className="btn btn-outline-primary" onClick={handleSearch}>
                    <i className="bi bi-search"></i> Buscar
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Lista de consultas */}
          <div className="card">
            <div className="card-body">
              {isLoading ? (
                <div className="text-center py-4">
                  <div className="spinner-border" role="status">
                    <span className="visually-hidden">Cargando...</span>
                  </div>
                  <p className="mt-2">Cargando consultas...</p>
                </div>
              ) : error ? (
                <div className="alert alert-danger">
                  <i className="bi bi-exclamation-triangle"></i>
                  {error}
                </div>
              ) : consultas.length === 0 ? (
                <div className="text-center py-4">
                  <i className="bi bi-inbox display-1 text-muted"></i>
                  <p className="mt-2">No hay consultas</p>
                </div>
              ) : (
                <>
                  <div className="table-responsive">
                    <table className="table table-hover">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Paciente</th>
                          <th>Fecha</th>
                          <th>Motivo</th>
                          <th>Estado</th>
                          <th>Médico</th>
                          <th>Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        {consultas.map((consulta) => (
                          <tr key={consulta.id}>
                            <td>#{consulta.id}</td>
                            <td>{consulta.paciente}</td>
                            <td>{formatDate(consulta.fecha)}</td>
                            <td>{consulta.motivo}</td>
                            <td>{getStatusBadge(consulta.status)}</td>
                            <td>{consulta.medico || 'Sin asignar'}</td>
                            <td>
                              <div className="btn-group btn-group-sm">
                                <button className="btn btn-outline-primary">
                                  <i className="bi bi-eye"></i>
                                </button>
                                <button className="btn btn-outline-success">
                                  <i className="bi bi-chat"></i>
                                </button>
                                <button className="btn btn-outline-warning">
                                  <i className="bi bi-pencil"></i>
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

export default Consultas;