import 'dart:async';

import 'package:speech_to_text/speech_to_text.dart';

/// Dictado en dispositivo para captura clínica (STT local).
class DeviceSpeechDictation {
  DeviceSpeechDictation() : _speech = SpeechToText();

  final SpeechToText _speech;
  bool _available = false;
  DateTime? _startedAt;
  String _localeId = 'es_AR';
  String _lastText = '';
  double _lastConfidence = 0;

  bool get isListening => _speech.isListening;

  Future<bool> initialize({String localeId = 'es_AR'}) async {
    _localeId = localeId;
    _available = await _speech.initialize(
      onError: (_) {},
      onStatus: (_) {},
    );
    return _available;
  }

  Future<void> start({
    required void Function(String text, double confidence) onPartial,
  }) async {
    if (!_available) {
      final ok = await initialize(localeId: _localeId);
      if (!ok) {
        throw StateError('Dictado por voz no disponible en este dispositivo');
      }
    }
    _startedAt = DateTime.now();
    _lastText = '';
    _lastConfidence = 0;
    await _speech.listen(
      localeId: _localeId,
      listenMode: ListenMode.confirmation,
      onResult: (result) {
        _lastText = result.recognizedWords;
        _lastConfidence = result.confidence;
        onPartial(_lastText, _lastConfidence);
      },
    );
  }

  Future<DeviceDictationResult> stop() async {
    await _speech.stop();
    final durationMs = _startedAt != null
        ? DateTime.now().difference(_startedAt!).inMilliseconds
        : 0;
    _startedAt = null;
    return DeviceDictationResult(
      text: _lastText,
      confidence: _lastConfidence > 0 ? _lastConfidence : 0.75,
      durationMs: durationMs,
      engine: 'speech_to_text',
      locale: _localeId,
    );
  }

  Future<void> cancel() async {
    await _speech.cancel();
    _startedAt = null;
  }
}

class DeviceDictationResult {
  const DeviceDictationResult({
    required this.text,
    required this.confidence,
    required this.durationMs,
    required this.engine,
    required this.locale,
  });

  final String text;
  final double confidence;
  final int durationMs;
  final String engine;
  final String locale;

  Map<String, dynamic> toSttPayload({double clientEditRatio = 0}) => {
        'provenance': 'device',
        'text': text,
        'confidence': confidence,
        'duration_ms': durationMs,
        'engine': engine,
        'locale': locale,
        if (clientEditRatio > 0) 'client_edit_ratio': clientEditRatio,
      };
}

/// Heurísticas locales alineadas con DeviceSttQualityAssessor (PHP).
class DeviceSttLocalQuality {
  static const double capturaMinConfidence = 0.85;

  static bool isAcceptable(String text, DeviceDictationResult meta) {
    final t = text.trim();
    if (t.length < 3) return false;
    if (meta.confidence > 0 && meta.confidence < capturaMinConfidence) {
      return false;
    }
    if (meta.durationMs > 0) {
      final words = t.split(RegExp(r'\s+')).where((w) => w.isNotEmpty).length;
      final minutes = meta.durationMs / 60000.0;
      if (minutes > 0 && words / minutes < 20) return false;
    }
    return true;
  }
}
