// lib/services/config_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared/shared.dart';

/// Respuesta del modo «opciones» de POST /sesion-operativa/establecer (body vacío).
class SessionWizardOptions {
  final List<Efector> efectores;
  final Map<int, List<Servicio>> serviciosPorEfector;
  final List<EncounterClass> encounterClasses;
  final List<EfectorConProblema> efectoresConProblemas;

  SessionWizardOptions({
    required this.efectores,
    required this.serviciosPorEfector,
    required this.encounterClasses,
    required this.efectoresConProblemas,
  });
}

class EfectorConProblema {
  final int? idEfector;
  final String? nombre;
  final String message;
  final List<String> contactosNombreCompleto;

  EfectorConProblema({
    this.idEfector,
    this.nombre,
    required this.message,
    this.contactosNombreCompleto = const [],
  });

  factory EfectorConProblema.fromJson(Map<String, dynamic> json) {
    final rawContact = json['contact'];
    final contactos = <String>[];
    if (rawContact is List) {
      for (final c in rawContact) {
        if (c is Map && c['nombre_completo'] != null) {
          contactos.add(c['nombre_completo'].toString());
        }
      }
    }
    return EfectorConProblema(
      idEfector: json['id_efector'] as int?,
      nombre: json['nombre'] as String?,
      message: (json['message'] as String?) ?? '',
      contactosNombreCompleto: contactos,
    );
  }
}

class ConfigService {
  String? authToken;

  ConfigService({this.authToken});

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Client': 'mobile',
    };
    if (authToken != null && authToken!.isNotEmpty) {
      headers['Authorization'] = 'Bearer $authToken';
    }
    return headers;
  }

  /// Catálogo público de encounter classes (pantallas que no pasan por el wizard).
  Future<List<EncounterClass>> getEncounterClasses() async {
    final response = await http.get(
      Uri.parse('${AppConfig.apiUrl}/catalogos/encounter-classes'),
      headers: _headers,
    );
    if (response.statusCode != 200) {
      final errorData = json.decode(response.body) as Map<String, dynamic>?;
      throw Exception(errorData?['message'] ?? 'Error al obtener encounter classes');
    }
    final data = json.decode(response.body) as Map<String, dynamic>;
    if (data['success'] != true || data['data'] == null) {
      throw Exception(data['message'] ?? 'Error al obtener encounter classes');
    }
    final raw = data['data']['encounter_classes'] as List<dynamic>? ?? [];
    return raw
        .map((c) => EncounterClass.fromJson(c as Map<String, dynamic>))
        .where((c) => c.code.isNotEmpty)
        .toList();
  }

  /// Modo opciones: un solo POST sin selección (mismo endpoint que establecer sesión).
  Future<SessionWizardOptions> loadSessionWizardOptions({String? userId}) async {
    final body = <String, dynamic>{};
    if (userId != null &&
        (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
      body['user_id'] = userId;
    }

    final uri = Uri.parse('${AppConfig.apiUrl}/sesion-operativa/establecer');
    print('Request URL: $uri (wizard options)');
    print('Headers: $_headers');

    final response = await http.post(
      uri,
      headers: _headers,
      body: json.encode(body),
    );

    print('Response status: ${response.statusCode}');

    final bodyTrimmed = response.body.trim();
    if (bodyTrimmed.startsWith('<!DOCTYPE') || bodyTrimmed.startsWith('<html')) {
      throw Exception(
          'La API devolvió HTML en lugar de JSON. Verifique URL y autenticación: $uri');
    }

    if (response.statusCode != 200) {
      try {
        final err = json.decode(response.body) as Map<String, dynamic>?;
        throw Exception(err?['message'] ?? 'Error ${response.statusCode}');
      } catch (_) {
        throw Exception('Error ${response.statusCode}: ${response.body}');
      }
    }

    final data = json.decode(response.body) as Map<String, dynamic>;
    if (data['success'] != true || data['data'] == null) {
      throw Exception(data['message'] ?? 'Error al cargar opciones de sesión');
    }

    final payload = data['data'] as Map<String, dynamic>;
    final rawEfectores = payload['efectores'] as List<dynamic>? ?? [];
    final efectores = <Efector>[];
    final serviciosPorEfector = <int, List<Servicio>>{};

    for (final e in rawEfectores) {
      if (e is! Map<String, dynamic>) continue;
      final ef = Efector.fromJson(e);
      if (ef.id <= 0) continue;
      efectores.add(ef);
      final rawServ = e['servicios'] as List<dynamic>? ?? [];
      final servicios = rawServ
          .map((s) => Servicio.fromJson(s as Map<String, dynamic>))
          .where((s) => s.id > 0)
          .toList();
      serviciosPorEfector[ef.id] = servicios;
    }

    final rawEc = payload['encounter_classes'] as List<dynamic>? ?? [];
    final encounterClasses = rawEc
        .map((c) => EncounterClass.fromJson(c as Map<String, dynamic>))
        .where((c) => c.code.isNotEmpty)
        .toList();

    final rawProb = payload['efectores_con_problemas'] as List<dynamic>? ?? [];
    final problemas = rawProb
        .map((p) => EfectorConProblema.fromJson(p as Map<String, dynamic>))
        .toList();

    return SessionWizardOptions(
      efectores: efectores,
      serviciosPorEfector: serviciosPorEfector,
      encounterClasses: encounterClasses,
      efectoresConProblemas: problemas,
    );
  }

  /// Obtener servicios de un efector (desde caché del wizard; no llama a listar-servicios-en-efector).
  List<Servicio> serviciosParaEfector(int efectorId, SessionWizardOptions options) {
    return options.serviciosPorEfector[efectorId] ?? [];
  }

  /// Establecer configuración de sesión
  Future<SessionConfig> setSession({
    required int efectorId,
    required int servicioId,
    required String encounterClass,
    String? userId,
  }) async {
    try {
      final body = <String, dynamic>{
        'efector_id': efectorId,
        'servicio_id': servicioId,
        'encounter_class': encounterClass,
      };

      if (userId != null &&
          (authToken == null || authToken!.isEmpty || authToken!.startsWith('simulated_'))) {
        body['user_id'] = userId;
      }

      print('Request URL: ${AppConfig.apiUrl}/sesion-operativa/establecer');
      print('Request body: $body');
      print('Headers: $_headers');

      final response = await http.post(
        Uri.parse('${AppConfig.apiUrl}/sesion-operativa/establecer'),
        headers: _headers,
        body: json.encode(body),
      );

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');

      if (response.statusCode == 200) {
        final data = json.decode(response.body) as Map<String, dynamic>;
        print('Parsed data: $data');

        if (data['success'] == true && data['data'] != null) {
          try {
            return SessionConfig.fromJson(data['data'] as Map<String, dynamic>);
          } catch (e) {
            print('Error parsing SessionConfig: $e');
            print('Data received: ${data['data']}');
            rethrow;
          }
        } else {
          throw Exception(data['message'] ?? 'Error al establecer configuración');
        }
      } else {
        final errorData = json.decode(response.body) as Map<String, dynamic>;
        var msg = errorData['message']?.toString() ?? 'Error al establecer configuración';
        final contact = errorData['contact'];
        if (contact is List && contact.isNotEmpty) {
          final nombres = contact
              .map((c) => c is Map ? c['nombre_completo']?.toString() : null)
              .whereType<String>()
              .where((s) => s.isNotEmpty)
              .toList();
          if (nombres.isNotEmpty) {
            msg += ' Contacto administración: ${nombres.join(', ')}';
          }
        }
        throw Exception(msg);
      }
    } catch (e) {
      print('Error setting session: $e');
      rethrow;
    }
  }
}

// Modelos
class Efector {
  final int id;
  final String nombre;
  final int? idLocalidad;

  Efector({
    required this.id,
    required this.nombre,
    this.idLocalidad,
  });

  factory Efector.fromJson(Map<String, dynamic> json) {
    String nombreStr;
    if (json['nombre'] is String) {
      nombreStr = json['nombre'] as String;
    } else if (json['nombre'] is Map) {
      nombreStr = (json['nombre'] as Map)['nombre'] as String? ?? 'Sin nombre';
    } else {
      nombreStr = 'Sin nombre';
    }

    return Efector(
      id: json['id_efector'] as int? ?? json['id'] as int? ?? 0,
      nombre: nombreStr,
      idLocalidad: json['id_localidad'] as int?,
    );
  }
}

class Servicio {
  final int id;
  final String nombre;
  final int idRrhhServicio;

  Servicio({
    required this.id,
    required this.nombre,
    required this.idRrhhServicio,
  });

  factory Servicio.fromJson(Map<String, dynamic> json) {
    return Servicio(
      id: (json['id'] as int?) ??
          (json['id_servicio'] as int?) ??
          (json['id_servicio'] is String ? int.tryParse(json['id_servicio'] as String) : null) ??
          0,
      nombre: json['nombre'] as String? ?? 'Sin nombre',
      idRrhhServicio: (json['id_rrhh_servicio'] as int?) ?? 0,
    );
  }
}

class EncounterClass {
  final String code;
  final String label;

  EncounterClass({
    required this.code,
    required this.label,
  });

  factory EncounterClass.fromJson(Map<String, dynamic> json) {
    return EncounterClass(
      code: json['code'] as String? ?? '',
      label: json['label'] as String? ?? '',
    );
  }
}

class SessionConfig {
  final Efector efector;
  final Servicio servicio;
  final EncounterClass encounterClass;
  final int rrhhId;
  final String? contextToken;

  SessionConfig({
    required this.efector,
    required this.servicio,
    required this.encounterClass,
    required this.rrhhId,
    this.contextToken,
  });

  factory SessionConfig.fromJson(Map<String, dynamic> json) {
    return SessionConfig(
      efector: Efector.fromJson(json['efector'] as Map<String, dynamic>),
      servicio: Servicio.fromJson(json['servicio'] as Map<String, dynamic>),
      encounterClass: EncounterClass.fromJson(json['encounter_class'] as Map<String, dynamic>),
      rrhhId: (json['rrhh_id'] as int?) ?? 0,
      contextToken: json['context_token'] as String?,
    );
  }
}
