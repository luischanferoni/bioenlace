// lib/services/config_service.dart
import 'dart:developer' as developer;
import 'package:shared/shared.dart';

void _logConfig(String message, {Object? error, StackTrace? stackTrace}) {
  developer.log(
    message,
    name: 'ConfigService',
    error: error,
    stackTrace: stackTrace,
  );
}

/// Respuesta del modo «opciones» de POST /sesion-operativa/establecer (body vacío).
class SessionWizardOptions {
  final List<Efector> efectores;
  final Map<int, List<Servicio>> serviciosPorEfector;
  final List<EfectorConProblema> efectoresConProblemas;
  final bool puedeCrearEfector;

  SessionWizardOptions({
    required this.efectores,
    required this.serviciosPorEfector,
    required this.efectoresConProblemas,
    this.puedeCrearEfector = false,
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

  ApiClient get _api => ApiClient(
    authToken: authToken,
    appClient: 'personalsalud-flutter',
    traceLabel: 'sesion-operativa',
  );

  /// Catálogo público de encounter classes (pantallas que no pasan por el wizard).
  Future<List<EncounterClass>> getEncounterClasses() async {
    final data = await _api.getData('/catalogos/encounter-classes');
    final raw = data['encounter_classes'] as List<dynamic>? ?? [];
    return raw
        .map((c) => EncounterClass.fromJson(c as Map<String, dynamic>))
        .where((c) => c.code.isNotEmpty)
        .toList();
  }

  /// Modo opciones: un solo POST sin selección (mismo endpoint que establecer sesión).
  Future<SessionWizardOptions> loadSessionWizardOptions({
    String? userId,
  }) async {
    final body = <String, dynamic>{};
    if (userId != null &&
        (authToken == null ||
            authToken!.isEmpty ||
            authToken!.startsWith('simulated_'))) {
      body['user_id'] = userId;
    }

    final payload = await _api.postData(
      '/sesion-operativa/establecer',
      body: body,
    );
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

    final rawProb = payload['efectores_con_problemas'] as List<dynamic>? ?? [];
    final problemas = rawProb
        .map((p) => EfectorConProblema.fromJson(p as Map<String, dynamic>))
        .toList();

    return SessionWizardOptions(
      efectores: efectores,
      serviciosPorEfector: serviciosPorEfector,
      efectoresConProblemas: problemas,
      puedeCrearEfector: payload['puede_crear_efector'] == true,
    );
  }

  /// Obtener servicios de un efector (desde caché del wizard; no llama a listar-servicios-en-efector).
  List<Servicio> serviciosParaEfector(
    int efectorId,
    SessionWizardOptions options,
  ) {
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
          (authToken == null ||
              authToken!.isEmpty ||
              authToken!.startsWith('simulated_'))) {
        body['user_id'] = userId;
      }

      final data = await _api.postData(
        '/sesion-operativa/establecer',
        body: body,
      );
      _logConfig('Parsed data: $data');

      try {
        return SessionConfig.fromJson(data);
      } catch (e, st) {
        _logConfig('Error parsing SessionConfig', error: e, stackTrace: st);
        _logConfig('Data received: $data');
        rethrow;
      }
    } catch (e, st) {
      if (e is ApiContractException) {
        final contact = e.meta['contact'];
        if (contact is List && contact.isNotEmpty) {
          final nombres = contact
              .map((c) => c is Map ? c['nombre_completo']?.toString() : null)
              .whereType<String>()
              .where((s) => s.isNotEmpty)
              .toList();
          if (nombres.isNotEmpty) {
            throw Exception(
              '${e.message} Contacto administración: ${nombres.join(', ')}',
            );
          }
        }
      }
      _logConfig('Error setting session', error: e, stackTrace: st);
      rethrow;
    }
  }
}

// Modelos
class Efector {
  final int id;
  final String nombre;
  final int? idLocalidad;
  final List<EncounterClass> encounterClasses;

  Efector({
    required this.id,
    required this.nombre,
    this.idLocalidad,
    this.encounterClasses = const [],
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
    final rawEncounterClasses =
        json['encounter_classes'] as List<dynamic>? ?? const [];

    return Efector(
      id: json['id_efector'] as int? ?? json['id'] as int? ?? 0,
      nombre: nombreStr,
      idLocalidad: json['id_localidad'] as int?,
      encounterClasses: rawEncounterClasses
          .whereType<Map<String, dynamic>>()
          .map(EncounterClass.fromJson)
          .where((encounterClass) => encounterClass.code.isNotEmpty)
          .toList(),
    );
  }
}

class Servicio {
  final int id;
  final String nombre;

  /// Asignación canónica (PES) para agenda y turnos.
  final int idProfesionalEfectorServicio;

  Servicio({
    required this.id,
    required this.nombre,
    this.idProfesionalEfectorServicio = 0,
  });

  factory Servicio.fromJson(Map<String, dynamic> json) {
    final idPesRaw = json['id_profesional_efector_servicio'];
    final idPes = idPesRaw is int
        ? idPesRaw
        : (idPesRaw is String ? int.tryParse(idPesRaw) : null) ?? 0;
    return Servicio(
      id:
          (json['id'] as int?) ??
          (json['id_servicio'] as int?) ??
          (json['id_servicio'] is String
              ? int.tryParse(json['id_servicio'] as String)
              : null) ??
          0,
      nombre: json['nombre'] as String? ?? 'Sin nombre',
      idProfesionalEfectorServicio: idPes,
    );
  }
}

class EncounterClass {
  final String code;
  final String label;
  final List<String> capabilities;

  EncounterClass({
    required this.code,
    required this.label,
    this.capabilities = const [],
  });

  factory EncounterClass.fromJson(Map<String, dynamic> json) {
    final rawCapabilities = json['capabilities'];
    return EncounterClass(
      code: json['code'] as String? ?? '',
      label: json['label'] as String? ?? '',
      capabilities: rawCapabilities is List
          ? rawCapabilities.whereType<String>().toList()
          : const [],
    );
  }
}

class SessionConfig {
  final Efector efector;
  final Servicio servicio;
  final EncounterClass encounterClass;

  /// Identificador PES del contexto operativo (equivale a `servicio.id_profesional_efector_servicio` cuando aplica).
  final int idProfesionalEfectorServicio;
  final String? contextToken;

  /// Ámbito de atención informado por el backend (`humano` o `veterinario`).
  final String ambitoAtencion;

  SessionConfig({
    required this.efector,
    required this.servicio,
    required this.encounterClass,
    required this.idProfesionalEfectorServicio,
    this.contextToken,
    this.ambitoAtencion = 'humano',
  });

  factory SessionConfig.fromJson(Map<String, dynamic> json) {
    final servicioMap = json['servicio'] as Map<String, dynamic>?;
    final idFromServicio = servicioMap != null
        ? (servicioMap['id_profesional_efector_servicio'] as int?) ?? 0
        : 0;
    final idCtx = (json['id_contexto_profesional'] as int?) ?? idFromServicio;

    return SessionConfig(
      efector: Efector.fromJson(json['efector'] as Map<String, dynamic>),
      servicio: Servicio.fromJson(json['servicio'] as Map<String, dynamic>),
      encounterClass: EncounterClass.fromJson(
        json['encounter_class'] as Map<String, dynamic>,
      ),
      idProfesionalEfectorServicio: idCtx,
      contextToken: json['context_token'] as String?,
      ambitoAtencion: json['ambito_atencion'] as String? ?? 'humano',
    );
  }
}
