import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'person_representation_api.dart';

/// Opción de sujeto en el selector «A cargo de».
class RepresentationSubjectOption {
  final int personaId;
  final String label;
  final String? regime;
  final String? status;

  const RepresentationSubjectOption({
    required this.personaId,
    required this.label,
    this.regime,
    this.status,
  });

  bool get isSelf => regime == null;
}

/// Contexto global del paciente sobre el que se opera (yo u otro con representación).
class PersonRepresentationContext extends ChangeNotifier {
  PersonRepresentationContext._();

  static final PersonRepresentationContext instance = PersonRepresentationContext._();

  static const _prefSubjectId = 'rep_subject_persona_id';
  static const _prefSubjectLabel = 'rep_subject_label';

  int _actorPersonaId = 0;
  String _actorLabel = 'Yo';
  int? _subjectPersonaId;
  String _subjectLabel = 'Yo';
  List<RepresentationSubjectOption> _options = const [];
  bool _loadingOptions = false;

  int get actorPersonaId => _actorPersonaId;
  String get actorLabel => _actorLabel;
  int? get subjectPersonaId => _subjectPersonaId;
  String get subjectLabel => _subjectLabel;
  List<RepresentationSubjectOption> get options => _options;
  bool get loadingOptions => _loadingOptions;

  bool get actingForOther =>
      _subjectPersonaId != null &&
      _actorPersonaId > 0 &&
      _subjectPersonaId != _actorPersonaId;

  /// Parámetros extra para propagar en body/query HTTP.
  Map<String, dynamic> extraRequestParams() {
    if (!actingForOther || _subjectPersonaId == null) {
      return {};
    }
    return {'subject_persona_id': _subjectPersonaId};
  }

  Future<void> bindActor({
    required int actorPersonaId,
    required String actorLabel,
    String? authToken,
  }) async {
    _actorPersonaId = actorPersonaId;
    _actorLabel = actorLabel.trim().isNotEmpty ? actorLabel.trim() : 'Yo';
    await _restoreFromPrefs();
    if (_subjectPersonaId == null) {
      _subjectLabel = _actorLabel;
    }
    notifyListeners();
    await refreshOptions(authToken: authToken);
  }

  Future<void> _restoreFromPrefs() async {
    final prefs = await SharedPreferences.getInstance();
    final storedId = prefs.getInt(_prefSubjectId);
    final storedLabel = prefs.getString(_prefSubjectLabel);
    if (storedId != null && storedId > 0 && storedId != _actorPersonaId) {
      _subjectPersonaId = storedId;
      _subjectLabel = storedLabel?.trim().isNotEmpty == true ? storedLabel!.trim() : 'Paciente';
    } else {
      _subjectPersonaId = null;
      _subjectLabel = _actorLabel;
    }
  }

  Future<void> refreshOptions({String? authToken}) async {
    if (_actorPersonaId <= 0) return;
    _loadingOptions = true;
    notifyListeners();

    final api = PersonRepresentationApi(authToken: authToken);
    final self = RepresentationSubjectOption(
      personaId: _actorPersonaId,
      label: _actorLabel,
    );
    final merged = <int, RepresentationSubjectOption>{_actorPersonaId: self};

    final cargo = await api.fetchPacientesACargo();
    if (cargo['success'] == true) {
      final data = cargo['data'];
      if (data is Map<String, dynamic>) {
        final pacientes = data['pacientes'];
        if (pacientes is List) {
          for (final raw in pacientes) {
            if (raw is! Map) continue;
            final link = Map<String, dynamic>.from(raw);
            final subjectId = _intFrom(link['subject_persona_id']);
            if (subjectId == null || subjectId <= 0 || subjectId == _actorPersonaId) {
              continue;
            }
            if ((link['status']?.toString() ?? '') != 'active') continue;
            final subject = link['subject'];
            final label = _personaLabel(subject is Map ? Map<String, dynamic>.from(subject) : null, subjectId);
            merged[subjectId] = RepresentationSubjectOption(
              personaId: subjectId,
              label: label,
              regime: link['regime']?.toString(),
              status: link['status']?.toString(),
            );
          }
        }
      }
    }

    final tutor = await api.fetchVinculosComoTutor(status: 'active');
    if (tutor['success'] == true) {
      final data = tutor['data'];
      if (data is Map<String, dynamic>) {
        final vinculos = data['vinculos'];
        if (vinculos is List) {
          for (final raw in vinculos) {
            if (raw is! Map) continue;
            final link = Map<String, dynamic>.from(raw);
            final subjectId = _intFrom(link['subject_persona_id']);
            if (subjectId == null || subjectId <= 0 || subjectId == _actorPersonaId) {
              continue;
            }
            if ((link['status']?.toString() ?? '') != 'active') continue;
            final subject = link['subject'];
            final label = _personaLabel(subject is Map ? Map<String, dynamic>.from(subject) : null, subjectId);
            merged[subjectId] = RepresentationSubjectOption(
              personaId: subjectId,
              label: label,
              regime: link['regime']?.toString() ?? 'verified_guardianship',
              status: link['status']?.toString(),
            );
          }
        }
      }
    }

    _options = merged.values.toList()
      ..sort((a, b) {
        if (a.isSelf) return -1;
        if (b.isSelf) return 1;
        return a.label.compareTo(b.label);
      });

    if (_subjectPersonaId != null) {
      final stillValid = _options.any((o) => o.personaId == _subjectPersonaId);
      if (!stillValid) {
        await clearSubject(authToken: authToken, syncServer: true);
      } else {
        final match = _options.firstWhere((o) => o.personaId == _subjectPersonaId);
        _subjectLabel = match.label;
      }
    }

    _loadingOptions = false;
    notifyListeners();
  }

  Future<void> selectSubject(
    RepresentationSubjectOption option, {
    String? authToken,
    bool syncServer = true,
  }) async {
    if (option.personaId == _actorPersonaId) {
      await clearSubject(authToken: authToken, syncServer: syncServer);
      return;
    }
    _subjectPersonaId = option.personaId;
    _subjectLabel = option.label;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_prefSubjectId, option.personaId);
    await prefs.setString(_prefSubjectLabel, option.label);
    notifyListeners();
    if (syncServer) {
      await PersonRepresentationApi(authToken: authToken)
          .establecerSujetoPaciente(option.personaId);
    }
  }

  Future<void> clearSubject({String? authToken, bool syncServer = true}) async {
    _subjectPersonaId = null;
    _subjectLabel = _actorLabel;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefSubjectId);
    await prefs.remove(_prefSubjectLabel);
    notifyListeners();
    if (syncServer) {
      await PersonRepresentationApi(authToken: authToken).establecerSujetoPaciente(null);
    }
  }

  Future<void> clearOnLogout() async {
    _actorPersonaId = 0;
    _actorLabel = 'Yo';
    _subjectPersonaId = null;
    _subjectLabel = 'Yo';
    _options = const [];
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefSubjectId);
    await prefs.remove(_prefSubjectLabel);
    notifyListeners();
  }

  static String _personaLabel(Map<String, dynamic>? subject, int fallbackId) {
    if (subject == null) return 'Paciente $fallbackId';
    final nombre = subject['nombre']?.toString().trim() ?? '';
    final apellido = subject['apellido']?.toString().trim() ?? '';
    final full = '$nombre $apellido'.trim();
    return full.isNotEmpty ? full : 'Paciente $fallbackId';
  }

  static int? _intFrom(dynamic raw) {
    if (raw is int) return raw;
    if (raw == null) return null;
    return int.tryParse(raw.toString());
  }
}
