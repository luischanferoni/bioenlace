import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:speech_to_text/speech_to_text.dart';

/// Modo de escucha (evita que apps cliente importen speech_to_text).
enum DeviceListenMode {
  /// Frases cortas / confirmación (captura clínica).
  confirmation,

  /// Frases más largas (motivos de consulta).
  dictation,
}

/// Dictado en dispositivo (STT local / motor del SO).
class DeviceSpeechDictation {
  DeviceSpeechDictation() : _speech = SpeechToText();

  final SpeechToText _speech;
  bool _available = false;
  DateTime? _startedAt;
  String _localeId = 'es_AR';
  String _lastText = '';
  double _lastConfidence = 0;
  bool _lastFinalResult = false;
  bool _usedOnDevice = false;
  String? _lastError;

  bool get isListening => _speech.isListening;
  bool get isAvailable => _available;

  Future<bool> initialize({String localeId = 'es_AR'}) async {
    _localeId = localeId;
    _available = await _speech.initialize(
      onError: (error) {
        _lastError = error.errorMsg;
        debugPrint('[stt-device] error=${error.errorMsg}');
      },
      onStatus: (status) {
        debugPrint('[stt-device] status=$status');
      },
    );
    debugPrint(
      '[stt-device] initialized available=$_available locale=$_localeId '
      'platform=${kIsWeb ? 'web' : defaultTargetPlatform.name}',
    );
    return _available;
  }

  /// [preferOnDevice]: pide motor offline si el SO lo permite; si falla, reintenta sin forzar.
  Future<void> start({
    required void Function(String text, double confidence) onPartial,
    DeviceListenMode listenMode = DeviceListenMode.confirmation,
    bool preferOnDevice = false,
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
    _lastFinalResult = false;
    _lastError = null;

    final mode = listenMode == DeviceListenMode.dictation
        ? ListenMode.dictation
        : ListenMode.confirmation;

    Future<void> listen({required bool onDevice}) {
      _usedOnDevice = onDevice;
      debugPrint(
        '[stt-device] listen locale=$_localeId mode=$mode '
        'onDevice=$onDevice platform=${kIsWeb ? 'web' : defaultTargetPlatform.name}',
      );
      return _speech.listen(
        listenOptions: SpeechListenOptions(
          localeId: _localeId,
          listenMode: mode,
          onDevice: onDevice,
          cancelOnError: true,
          partialResults: true,
        ),
        onResult: (result) {
          _lastText = result.recognizedWords;
          _lastConfidence = result.confidence;
          _lastFinalResult = result.finalResult;
          debugPrint(
            '[stt-device] result chars=${_lastText.length} '
            'words=${_wordCount(_lastText)} confidence=${_lastConfidence.toStringAsFixed(3)} '
            'final=${result.finalResult}',
          );
          onPartial(_lastText, _lastConfidence);
        },
      );
    }

    try {
      await listen(onDevice: preferOnDevice);
    } catch (e) {
      if (preferOnDevice) {
        debugPrint('[stt-device] on-device listen failed; retrying default engine: $e');
        await listen(onDevice: false);
      } else {
        rethrow;
      }
    }
  }

  Future<DeviceDictationResult> stop() async {
    await _speech.stop();
    final durationMs = _startedAt != null
        ? DateTime.now().difference(_startedAt!).inMilliseconds
        : 0;
    _startedAt = null;
    final result = DeviceDictationResult(
      text: _lastText,
      confidence: _lastConfidence,
      durationMs: durationMs,
      engine: 'speech_to_text',
      locale: _localeId,
      finalResult: _lastFinalResult,
      onDevice: _usedOnDevice,
      error: _lastError,
    );
    debugPrint(
      '[stt-device] stopped durationMs=$durationMs chars=${_lastText.length} '
      'words=${_wordCount(_lastText)} confidence=${_lastConfidence.toStringAsFixed(3)} '
      'final=$_lastFinalResult onDevice=$_usedOnDevice error=${_lastError ?? 'none'}',
    );
    return result;
  }

  Future<void> cancel() async {
    await _speech.cancel();
    _startedAt = null;
  }

  static int _wordCount(String text) =>
      text.trim().isEmpty ? 0 : text.trim().split(RegExp(r'\s+')).length;
}

class DeviceDictationResult {
  const DeviceDictationResult({
    required this.text,
    required this.confidence,
    required this.durationMs,
    required this.engine,
    required this.locale,
    this.finalResult = false,
    this.onDevice = false,
    this.error,
  });

  final String text;
  final double confidence;
  final int durationMs;
  final String engine;
  final String locale;
  final bool finalResult;
  final bool onDevice;
  final String? error;

  Map<String, dynamic> toSttPayload({double clientEditRatio = 0}) => {
        'provenance': 'device',
        'text': text,
        'confidence': confidence,
        'duration_ms': durationMs,
        'engine': engine,
        'locale': locale,
        'final_result': finalResult,
        'on_device': onDevice,
        if (error != null && error!.isNotEmpty) 'error': error,
        if (clientEditRatio > 0) 'client_edit_ratio': clientEditRatio,
      };
}

/// Heurísticas locales alineadas con DeviceSttQualityAssessor (PHP).
class DeviceSttLocalQuality {
  static const double capturaMinConfidence = 0.85;
  static const double motivosMinConfidence = 0.75;

  static bool isAcceptable(
    String text,
    DeviceDictationResult meta, {
    double minConfidence = capturaMinConfidence,
  }) {
    final t = text.trim();
    if (t.length < 3) return false;
    if (meta.error != null && meta.error!.isNotEmpty) return false;
    if (meta.confidence > 0 && meta.confidence < minConfidence) {
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
