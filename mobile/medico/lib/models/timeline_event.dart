// lib/models/timeline_event.dart
class TimelineEvent {
  final int id;
  final String tipo; // Turno, Consulta, Internacion, Guardia, DocumentoExterno, etc.
  final String fecha;
  final String? resumen;
  final String? servicio;
  final String? idServicio;
  final String? parentClass;
  final int? parentId;
  final String? profesional;
  final int? idRrHh;
  final String? efector;
  final String? tipoDetalle;

  TimelineEvent({
    required this.id,
    required this.tipo,
    required this.fecha,
    this.resumen,
    this.servicio,
    this.idServicio,
    this.parentClass,
    this.parentId,
    this.profesional,
    this.idRrHh,
    this.efector,
    this.tipoDetalle,
  });

  // Crear desde JSON de la API
  factory TimelineEvent.fromJson(Map<String, dynamic> json) {
    return TimelineEvent(
      id: json['id'] as int,
      tipo: json['tipo'] as String? ?? 'Desconocido',
      fecha: json['fecha'] as String,
      resumen: json['resumen'] as String?,
      servicio: json['servicio'] as String?,
      idServicio: json['id_servicio'] as String?,
      parentClass: json['parent_class'] as String?,
      parentId: json['parent_id'] as int?,
      profesional: json['profesional'] as String?,
      idRrHh: json['id_rr_hh'] as int?,
      efector: json['efector'] as String?,
      tipoDetalle: json['tipo_detalle'] as String?,
    );
  }

  // Convertir a JSON
  Map<String, dynamic> toJson() => {
        'id': id,
        'tipo': tipo,
        'fecha': fecha,
        'resumen': resumen,
        'servicio': servicio,
        'id_servicio': idServicio,
        'parent_class': parentClass,
        'parent_id': parentId,
        'profesional': profesional,
        'id_rr_hh': idRrHh,
        'efector': efector,
        'tipo_detalle': tipoDetalle,
      };

  // Obtener fecha como DateTime
  DateTime? get fechaDateTime {
    try {
      // Intentar parsear diferentes formatos de fecha
      if (fecha.contains(' ')) {
        // Formato: "2024-01-01 10:30:00"
        return DateTime.parse(fecha);
      } else {
        // Formato: "2024-01-01"
        return DateTime.parse(fecha);
      }
    } catch (e) {
      return null;
    }
  }

  // Obtener icono según el tipo
  String get icono {
    switch (tipo) {
      case 'Turno':
        return 'calendar-check';
      case 'Consulta':
        return 'file-medical';
      case 'Internacion':
        return 'hospital';
      case 'Guardia':
        return 'ambulance';
      case 'DocumentoExterno':
        return 'file-alt';
      case 'EncuestaParchesMamarios':
        return 'clipboard-list';
      case 'EstudiosImagenes':
        return 'x-ray';
      case 'EstudiosLab':
        return 'flask';
      case 'Forms':
        return 'file-invoice';
      default:
        return 'circle';
    }
  }

  // Obtener color según el tipo
  String get color {
    switch (tipo) {
      case 'Turno':
        return 'primary';
      case 'Consulta':
        return 'info';
      case 'Internacion':
        return 'warning';
      case 'Guardia':
        return 'danger';
      case 'DocumentoExterno':
        return 'secondary';
      default:
        return 'dark';
    }
  }
}

// Modelo para información médica del paciente
class InformacionMedica {
  final List<Condicion> condicionesActivas;
  final List<Condicion> condicionesCronicas;
  final List<Hallazgo> hallazgos;
  final List<Antecedente> antecedentesPersonales;
  final List<Antecedente> antecedentesFamiliares;

  InformacionMedica({
    required this.condicionesActivas,
    required this.condicionesCronicas,
    required this.hallazgos,
    required this.antecedentesPersonales,
    required this.antecedentesFamiliares,
  });

  factory InformacionMedica.fromJson(Map<String, dynamic> json) {
    return InformacionMedica(
      condicionesActivas: (json['condiciones_activas'] as List<dynamic>?)
              ?.map((e) => Condicion.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      condicionesCronicas: (json['condiciones_cronicas'] as List<dynamic>?)
              ?.map((e) => Condicion.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      hallazgos: (json['hallazgos'] as List<dynamic>?)
              ?.map((e) => Hallazgo.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      antecedentesPersonales: (json['antecedentes_personales'] as List<dynamic>?)
              ?.map((e) => Antecedente.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
      antecedentesFamiliares: (json['antecedentes_familiares'] as List<dynamic>?)
              ?.map((e) => Antecedente.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }
}

class Condicion {
  final String? codigo;
  final String? termino;

  Condicion({this.codigo, this.termino});

  factory Condicion.fromJson(Map<String, dynamic> json) {
    return Condicion(
      codigo: json['codigo'] as String?,
      termino: json['termino'] as String?,
    );
  }
}

class Hallazgo {
  final int? id;
  final String? codigo;
  final String? termino;

  Hallazgo({this.id, this.codigo, this.termino});

  factory Hallazgo.fromJson(Map<String, dynamic> json) {
    return Hallazgo(
      id: json['id'] as int?,
      codigo: json['codigo'] as String?,
      termino: json['termino'] as String?,
    );
  }
}

class Antecedente {
  final int? id;
  final String? situacion;

  Antecedente({this.id, this.situacion});

  factory Antecedente.fromJson(Map<String, dynamic> json) {
    return Antecedente(
      id: json['id'] as int?,
      situacion: json['situacion'] as String?,
    );
  }
}

