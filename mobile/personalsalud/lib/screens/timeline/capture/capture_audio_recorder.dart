import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../../../infra/app_texts.dart';

/// Grabación de audio de la captura: solo el acceso al micrófono y al archivo,
/// sin estado de pantalla ni mensajes.
class CaptureAudioRecorder {
  // Perezoso: crearlo toca la plataforma y no debe pasar al construir el controlador.
  late final AudioRecorder _recorder = AudioRecorder();

  Future<bool> hasPermission() => _recorder.hasPermission();

  /// Inicia la grabación y devuelve la ruta del archivo; lanza si no arrancó.
  Future<String> start() async {
    final extension = kIsWeb ? 'webm' : 'm4a';
    final filename =
        'encounter_${DateTime.now().millisecondsSinceEpoch}.$extension';
    final path = kIsWeb
        ? filename
        : '${(await getTemporaryDirectory()).path}/$filename';
    await _recorder.start(
      RecordConfig(
        encoder: kIsWeb ? AudioEncoder.opus : AudioEncoder.aacLc,
        sampleRate: 44100,
        numChannels: 1,
        androidConfig: const AndroidRecordConfig(useLegacy: true),
      ),
      path: path,
    );
    if (!await _recorder.isRecording()) {
      throw StateError(
        AppTexts.audioRecordingNotStarted,
      );
    }
    return path;
  }

  /// Detiene la grabación y devuelve la ruta final (null si no hubo audio).
  Future<String?> stop() => _recorder.stop();
}
