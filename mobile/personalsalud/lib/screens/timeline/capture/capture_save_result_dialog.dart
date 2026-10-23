import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';

/// Diálogo modal con el resultado del guardado de una captura.
Future<void> showCaptureSaveResultDialog(
  BuildContext context, {
  required String titulo,
  required String mensaje,
  required UiIntent intent,
  String? detalle,
}) {
  final palette = IntentPalette.of(intent);
  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) => AlertDialog(
      title: Text(titulo),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(mensaje),
            if (detalle != null && detalle.trim().isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(detalle, style: Theme.of(ctx).textTheme.bodySmall),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(ctx).pop(),
          style: TextButton.styleFrom(foregroundColor: palette.base),
          child: const Text(AppTexts.understood),
        ),
      ],
    ),
  );
}

/// Resumen de diagnóstico técnico devuelto por el backend al guardar.
String captureSaveDiagnosticDetail(Map<String, dynamic> guardado) {
  final parts = <String>[];
  final persistido = guardado['persistido'];
  if (persistido is Map) {
    parts.add('persistido: $persistido');
  }
  final diag = guardado['diagnostico_guardar'];
  if (diag is Map) {
    parts.add('staged: ${diag['staged_counts']}');
    parts.add('final: ${diag['final_counts']}');
    parts.add('backup: ${diag['backup_fuentes']}');
    parts.add('cache: ${diag['cache']}');
    parts.add('por_modelo: ${diag['por_modelo']}');
  }
  if (guardado['log_id'] != null) {
    parts.add('log_id: ${guardado['log_id']}');
  }
  return parts.join('\n');
}
