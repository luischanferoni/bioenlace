import 'package:flutter/material.dart';

import '../../../infra/app_texts.dart';

/// Diálogo de permiso de micrófono. [onRetry] se ejecuta tras cerrarlo al
/// pulsar el botón de reintento.
Future<void> showMicrophonePermissionDialog(
  BuildContext context, {
  required Future<void> Function() onRetry,
  bool permissionRequestFailed = false,
}) {
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: const Icon(Icons.mic_off_outlined),
      title: const Text(AppTexts.micDisabledTitle),
      content: Text(
        permissionRequestFailed
            ? 'No se pudo habilitar el micrófono. Verificá que haya uno disponible y que el permiso esté habilitado en la configuración del navegador o del dispositivo. Después, volvé a intentar.'
            : 'Para grabar, habilitá el acceso al micrófono. Podés solicitar el permiso ahora. Si no aparece el aviso, revisá los permisos del navegador o del dispositivo.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(),
          child: const Text(AppTexts.close),
        ),
        FilledButton.icon(
          onPressed: () async {
            Navigator.of(dialogContext).pop();
            await onRetry();
          },
          icon: const Icon(Icons.mic),
          label: Text(
            permissionRequestFailed ? 'Volver a intentar' : 'Habilitar micrófono',
          ),
        ),
      ],
    ),
  );
}

/// Mensaje legible para un error al iniciar la grabación en el dispositivo.
String recordingStartErrorMessage(Object e) {
  final error = e.toString().toLowerCase();
  if (error.contains('notallowederror') ||
      error.contains('permission denied') ||
      error.contains('denied')) {
    return 'El navegador bloqueó el acceso al micrófono. Revisá los permisos y volvé a intentar.';
  }
  if (error.contains('notfounderror') ||
      error.contains('devicesnotfounderror')) {
    return 'No se encontró un micrófono disponible.';
  }
  return 'No se pudo grabar audio: $e';
}
