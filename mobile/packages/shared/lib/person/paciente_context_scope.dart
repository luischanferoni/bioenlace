import 'package:flutter/foundation.dart';
import 'package:flutter/scheduler.dart';

import 'paciente_context_api.dart';

/// Estado del contexto operativo del paciente (sector + provincia de contexto).
class PacienteContextState {
  final String sectorSalud;
  final int? idProvinciaContexto;
  final String? provinciaNombre;
  final String domicilioEstado;
  final bool puedeOperar;
  final Map<String, dynamic>? banner;

  const PacienteContextState({
    this.sectorSalud = 'publico',
    this.idProvinciaContexto,
    this.provinciaNombre,
    this.domicilioEstado = 'pendiente',
    this.puedeOperar = false,
    this.banner,
  });

  factory PacienteContextState.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const PacienteContextState();
    final provincia = json['provincia'];
    String? nombre;
    if (provincia is Map) {
      nombre = provincia['nombre']?.toString();
    }
    final banner = json['banner'];
    return PacienteContextState(
      sectorSalud: (json['sector_salud'] ?? 'publico').toString(),
      idProvinciaContexto: json['id_provincia_contexto'] as int?,
      provinciaNombre: nombre,
      domicilioEstado: (json['domicilio_estado'] ?? 'pendiente').toString(),
      puedeOperar: json['puede_operar'] == true,
      banner: banner is Map<String, dynamic> ? banner : null,
    );
  }

  bool get muestraBannerVerificando =>
      domicilioEstado == 'pendiente' && !puedeOperar;

  bool get requiereProvinciaManual =>
      domicilioEstado == 'requiere_provincia_manual' && !puedeOperar;
}

/// Mensajes que el banner superior ya muestra; no repetir en el chat.
bool pacienteContextShouldSuppressMessageInChat(String message) {
  final banner = PacienteContextScope.instance.state.banner;
  if (banner == null) return false;
  if (banner['kind']?.toString() != 'domicilio_pendiente') return false;
  final bannerMsg = (banner['message']?.toString() ?? '').trim();
  return bannerMsg.isNotEmpty && message.trim() == bannerMsg;
}

/// Contexto global del paciente (persistido en BD, cache en memoria).
class PacienteContextScope extends ChangeNotifier {
  PacienteContextScope._();

  static final PacienteContextScope instance = PacienteContextScope._();

  PacienteContextState _state = const PacienteContextState();
  bool _loading = false;
  String? _authToken;

  PacienteContextState get state => _state;
  bool get loading => _loading;
  bool get puedeOperar => _state.puedeOperar;

  void _notifyListenersSafely() {
    final phase = SchedulerBinding.instance.schedulerPhase;
    if (phase == SchedulerPhase.idle ||
        phase == SchedulerPhase.postFrameCallbacks) {
      notifyListeners();
      return;
    }
    SchedulerBinding.instance.addPostFrameCallback((_) {
      notifyListeners();
    });
  }

  void bindAuthToken(String? token) {
    _authToken = token;
  }

  Future<void> refresh({String? authToken}) async {
    final token = authToken ?? _authToken;
    if (token == null || token.isEmpty) return;
    _loading = true;
    try {
      final api = PacienteContextApi(authToken: token);
      final res = await api.fetchContexto();
      if (res['success'] == true) {
        final data = res['data'];
        final ctx = data is Map ? data['contexto'] : null;
        if (ctx is Map<String, dynamic>) {
          _state = PacienteContextState.fromJson(ctx);
        }
      }
    } finally {
      _loading = false;
      _notifyListenersSafely();
    }
  }

  void applyFromRegistration(Map<String, dynamic>? contextoJson) {
    if (contextoJson == null) return;
    _state = PacienteContextState.fromJson(contextoJson);
    _notifyListenersSafely();
  }

  Future<bool> actualizarProvincia(int idProvincia, {String? authToken}) async {
    final token = authToken ?? _authToken;
    if (token == null || token.isEmpty) return false;
    final api = PacienteContextApi(authToken: token);
    final res = await api.actualizarContexto(idProvinciaContexto: idProvincia);
    if (res['success'] == true) {
      final ctx = res['data']?['contexto'];
      if (ctx is Map<String, dynamic>) {
        _state = PacienteContextState.fromJson(ctx);
        _notifyListenersSafely();
        return true;
      }
    }
    return false;
  }

  Future<bool> actualizarSector(String sector, {String? authToken}) async {
    final token = authToken ?? _authToken;
    if (token == null || token.isEmpty) return false;
    final api = PacienteContextApi(authToken: token);
    final res = await api.actualizarContexto(sectorSalud: sector);
    if (res['success'] == true) {
      final ctx = res['data']?['contexto'];
      if (ctx is Map<String, dynamic>) {
        _state = PacienteContextState.fromJson(ctx);
        _notifyListenersSafely();
        return true;
      }
    }
    return false;
  }

  void clearOnLogout() {
    _state = const PacienteContextState();
    _authToken = null;
    _notifyListenersSafely();
  }
}
