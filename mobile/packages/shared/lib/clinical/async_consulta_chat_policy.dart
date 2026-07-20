import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';

/// Política de chat async devuelta por GET consulta-chat/mensajes → data.chat_policy.
class AsyncConsultaChatPolicy {
  const AsyncConsultaChatPolicy({
    required this.composerEnabled,
    required this.uploadEnabled,
    required this.uploadTypes,
    required this.hint,
    required this.canCancel,
    required this.canClose,
    required this.resolutions,
    required this.suggestTurno,
  });

  final bool composerEnabled;
  final bool uploadEnabled;
  final List<String> uploadTypes;
  final String hint;
  final bool canCancel;
  final bool canClose;
  final List<MapEntry<String, String>> resolutions;
  final bool suggestTurno;

  bool get canUploadAudio => uploadEnabled && uploadTypes.contains('audio');

  bool get canUploadDocument => uploadEnabled && uploadTypes.contains('documento');

  factory AsyncConsultaChatPolicy.fromApi(Map<String, dynamic>? raw) {
    if (raw == null) {
      return const AsyncConsultaChatPolicy(
        composerEnabled: true,
        uploadEnabled: false,
        uploadTypes: [],
        hint: '',
        canCancel: false,
        canClose: false,
        resolutions: [],
        suggestTurno: false,
      );
    }
    final composer = raw['composer'];
    final enabled = composer is Map && composer['enabled'] == true;
    final uploadTypes = <String>[];
    if (composer is Map && composer['upload_types'] is List) {
      for (final t in composer['upload_types'] as List) {
        final s = t?.toString().trim() ?? '';
        if (s.isNotEmpty) uploadTypes.add(s);
      }
    }
    final upload = composer is Map && composer['upload_enabled'] == true && uploadTypes.isNotEmpty;
    final hint = composer is Map ? composer['hint']?.toString().trim() ?? '' : '';
    final acciones = raw['acciones'] is Map
        ? Map<String, dynamic>.from(raw['acciones'] as Map)
        : <String, dynamic>{};
    final resRaw = raw['resoluciones_disponibles'];
    final resolutions = <MapEntry<String, String>>[];
    if (resRaw is Map) {
      resRaw.forEach((k, v) {
        final label = v?.toString().trim() ?? '';
        if (label.isNotEmpty) {
          resolutions.add(MapEntry(k.toString(), label));
        }
      });
    }

    return AsyncConsultaChatPolicy(
      composerEnabled: enabled,
      uploadEnabled: upload,
      uploadTypes: uploadTypes,
      hint: hint,
      canCancel: acciones['cancelar'] == true,
      canClose: acciones['cerrar'] == true,
      resolutions: resolutions,
      suggestTurno: raw['suggest_turno'] == true,
    );
  }
}

/// Etiqueta de adjunto para burbujas de chat async.
String asyncChatAttachmentLabel(String? messageType) {
  switch (messageType) {
    case 'audio':
      return 'Mensaje de audio';
    case 'documento':
      return 'Documento PDF';
    case 'imagen':
      return 'Imagen';
    case 'video':
      return 'Video';
    default:
      return 'Adjunto';
  }
}

/// Estilo de burbuja según message_kind / message_type del API.
TextStyle asyncChatMessageTextStyle(BuildContext context, Map<String, dynamic> message) {
  final kind = message['message_kind']?.toString() ?? '';
  final type = message['message_type']?.toString() ?? '';
  final base = BioTypography.bodySm.copyWith(color: context.bio.textBody);
  if (kind == 'sistema' || type == 'sistema') {
    return base.copyWith(
      fontStyle: FontStyle.italic,
      color: context.bio.textMuted,
    );
  }
  if (kind == 'solicitud' ||
      type.startsWith('solicitud_')) {
    return base.copyWith(fontWeight: FontWeight.w600);
  }

  return base;
}

Decoration? asyncChatMessageDecoration(BuildContext context, Map<String, dynamic> message) {
  final kind = message['message_kind']?.toString() ?? '';
  final type = message['message_type']?.toString() ?? '';
  if (kind == 'solicitud' || type.startsWith('solicitud_')) {
    return BoxDecoration(
      border: Border(
        left: BorderSide(
          color: IntentPalette.of(UiIntent.primary).base,
          width: 3,
        ),
      ),
    );
  }

  return null;
}

bool asyncChatMessageIsSystem(Map<String, dynamic> message) {
  final kind = message['message_kind']?.toString() ?? '';
  final type = message['message_type']?.toString() ?? '';
  return kind == 'sistema' || type == 'sistema';
}
