// lib/models/turno.dart
class Turno {
  final int id;
  final int idPersona;
  final Paciente? paciente;
  final String fecha;
  final String hora;
  final String? servicio;
  final int? idServicioAsignado;
  final String estado;
  final String estadoLabel;
  final String? observaciones;
  final String? atendido;
  final String? createdAt;
  final int? idConsulta;
  final String? tipoAtencion;

  Turno({
    required this.id,
    required this.idPersona,
    this.paciente,
    required this.fecha,
    required this.hora,
    this.servicio,
    this.idServicioAsignado,
    required this.estado,
    required this.estadoLabel,
    this.observaciones,
    this.atendido,
    this.createdAt,
    this.idConsulta,
    this.tipoAtencion,
  });

  static int _asInt(Object? v) {
    if (v is int) return v;
    if (v is String) return int.tryParse(v) ?? 0;
    return int.tryParse('$v') ?? 0;
  }

  static String _horaSinSegundos(String hora) {
    final trimmed = hora.trim();
    if (trimmed.isEmpty) return '';
    final match = RegExp(r'^(\d{1,2}:\d{2})').firstMatch(trimmed);
    return match?.group(1) ?? trimmed;
  }

  // Crear desde JSON de la API
  factory Turno.fromJson(Map<String, dynamic> json) {
    final idVal = json['id'] ?? json['id_turnos'];
    return Turno(
      id: _asInt(idVal),
      idPersona: _asInt(json['id_persona']),
      paciente: json['paciente'] != null
          ? Paciente.fromJson(json['paciente'] as Map<String, dynamic>)
          : null,
      fecha: json['fecha'] as String,
      hora: _horaSinSegundos(json['hora'] as String? ?? ''),
      servicio: json['servicio'] as String?,
      idServicioAsignado: json['id_servicio_asignado'] != null
          ? _asInt(json['id_servicio_asignado'])
          : null,
      estado: json['estado'] as String? ?? 'PENDIENTE',
      estadoLabel: json['estado_label'] as String? ?? 'Pendiente',
      observaciones: json['observaciones'] as String?,
      atendido: json['atendido'] as String?,
      createdAt: json['created_at'] as String?,
      idConsulta:
          json['id_consulta'] != null ? _asInt(json['id_consulta']) : null,
      tipoAtencion: json['tipo_atencion'] as String?,
    );
  }

  // Convertir a JSON
  Map<String, dynamic> toJson() => {
        'id': id,
        'id_persona': idPersona,
        'paciente': paciente?.toJson(),
        'fecha': fecha,
        'hora': hora,
        'servicio': servicio,
        'id_servicio_asignado': idServicioAsignado,
        'estado': estado,
        'estado_label': estadoLabel,
        'observaciones': observaciones,
        'atendido': atendido,
        'created_at': createdAt,
      };

  // Obtener fecha y hora como DateTime
  DateTime? get fechaHora {
    try {
      return DateTime.parse('$fecha $hora');
    } catch (e) {
      return null;
    }
  }

  // Verificar si es hoy
  bool get esHoy {
    final hoy = DateTime.now();
    try {
      final fechaTurno = DateTime.parse(fecha);
      return fechaTurno.year == hoy.year &&
          fechaTurno.month == hoy.month &&
          fechaTurno.day == hoy.day;
    } catch (e) {
      return false;
    }
  }
}

class Paciente {
  final int? id;
  final String nombreCompleto;
  final String? documento;
  final int? edad;

  Paciente({
    this.id,
    required this.nombreCompleto,
    this.documento,
    this.edad,
  });

  factory Paciente.fromJson(Map<String, dynamic> json) {
    final edadRaw = json['edad'];
    int? edad;
    if (edadRaw is int) {
      edad = edadRaw;
    } else if (edadRaw != null) {
      edad = int.tryParse('$edadRaw');
    }
    return Paciente(
      id: json['id'] as int?,
      nombreCompleto: json['nombre_completo'] as String? ?? 'Sin paciente',
      documento: json['documento'] as String?,
      edad: edad,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'nombre_completo': nombreCompleto,
        'documento': documento,
        'edad': edad,
      };
}

