import 'package:flutter/foundation.dart';
import 'package:shared/shared.dart';

import '../../../infra/app_texts.dart';
import 'capture_audio_recorder.dart';
import 'microphone_permission_dialog.dart';

/// Qué necesita la captura de voz de la pantalla que la aloja.
class CaptureDictationHost {
  const CaptureDictationHost({
    required this.getText,
    required this.setText,
    required this.notify,
    required this.showPermissionDialog,
    required this.saveDraft,
    required this.isBusy,
    required this.hasCaptureContext,
  });

  final String Function() getText;
  final void Function(String text) setText;
  final void Function(String message, UiIntent intent) notify;
  final Future<void> Function({bool permissionRequestFailed}) showPermissionDialog;

  /// Guarda el borrador local con el texto dictado (o un marcador si solo hay audio).
  final Future<void> Function(String text) saveDraft;
  final bool Function() isBusy;
  final bool Function() hasCaptureContext;
}

/// Estado y flujo del dictado por voz y la grabación de audio de la captura.
class CaptureDictationController extends ChangeNotifier {
  CaptureDictationController(this._host);

  final CaptureDictationHost _host;
  late final DeviceSpeechDictation _dictation = DeviceSpeechDictation();
  final CaptureAudioRecorder _recorder = CaptureAudioRecorder();

  SttClientConfig config = SttClientConfig.defaults;
  bool configLoaded = false;
  bool dictating = false;
  bool audioOnlyRecording = false;
  String? pendingAudioPath;
  DeviceDictationResult? lastDictation;
  String status = '';
  bool _disposed = false;

  bool get recording => dictating || audioOnlyRecording;
  bool get voiceAvailable =>
      configLoaded && (config.deviceEnabled || config.serverEnabled);

  void _changed() {
    if (!_disposed) notifyListeners();
  }

  void applyConfig(SttClientConfig cfg) {
    config = cfg;
    configLoaded = true;
    status = '';
    _changed();
  }

  void markConfigUnavailable() {
    config = SttClientConfig.unavailable;
    configLoaded = true;
    status = AppTexts.voiceConfigLoadFailed;
    _changed();
  }

  /// Limpia el estado de voz al descartar o reiniciar la captura.
  void reset({String nextStatus = ''}) {
    lastDictation = null;
    pendingAudioPath = null;
    status = nextStatus;
    _changed();
  }

  void setStatus(String value) {
    status = value;
    _changed();
  }

  Future<void> toggle() async {
    if (_host.isBusy()) return;
    if (!configLoaded) {
      _host.notify(AppTexts.voiceConfigLoading, UiIntent.warning);
      return;
    }
    if (recording) {
      await _stopCurrent();
      return;
    }
    if (!config.deviceEnabled && !config.serverEnabled) {
      _host.notify(AppTexts.dictationDisabled, UiIntent.warning);
      return;
    }
    if (!config.deviceEnabled) {
      await startAudioOnly();
      return;
    }
    await _startDeviceDictation();
  }

  Future<void> _stopCurrent() async {
    if (audioOnlyRecording) {
      await stopAudio();
      return;
    }
    final result = await _dictation.stop();
    final audioStopped = await stopAudio();
    if (_disposed) return;
    dictating = false;
    lastDictation = result;
    final dictated = result.text.trim();
    if (dictated.isNotEmpty) _host.setText(dictated);
    status = _host.getText().trim().isEmpty
        ? AppTexts.voiceNoSpeech
        : AppTexts.voiceReady;
    _changed();
    if (!audioStopped) return;
    final text = _host.getText().trim();
    if (_host.hasCaptureContext() && (text.isNotEmpty || pendingAudioPath != null)) {
      await _host.saveDraft(text.isEmpty ? AppTexts.voiceAudioPendingText : text);
    }
  }

  Future<void> _startDeviceDictation() async {
    try {
      final audioStarted = await startAudioOnly(audioOnly: false);
      if (!audioStarted) return;
      await _dictation.start(
        onPartial: (text, _) {
          if (_disposed) return;
          _host.setText(text);
          status = AppTexts.voiceListening;
          _changed();
        },
        listenMode: DeviceListenMode.dictation,
        preferOnDevice: true,
      );
      if (_disposed) {
        await _recorder.stop();
        return;
      }
      dictating = true;
      status = AppTexts.voiceListening;
      _changed();
    } catch (e, st) {
      await stopAudio();
      debugPrint('[stt-device] start failed: $e\n$st');
      _host.notify(AppTexts.dictationStartFailed(e), UiIntent.danger);
    }
  }

  Future<bool> startAudioOnly({bool audioOnly = true}) async {
    try {
      if (!await _recorder.hasPermission()) {
        if (kIsWeb) {
          await _host.showPermissionDialog(permissionRequestFailed: true);
        } else {
          _host.notify(AppTexts.microphonePermissionNeeded, UiIntent.danger);
        }
        return false;
      }
      final path = await _recorder.start();
      pendingAudioPath = path;
      if (_disposed) {
        await _recorder.stop();
        return false;
      }
      audioOnlyRecording = audioOnly;
      status = AppTexts.voiceRecording;
      _changed();
      return true;
    } catch (e) {
      if (kIsWeb) {
        await _host.showPermissionDialog();
        return false;
      }
      _host.notify(recordingStartErrorMessage(e), UiIntent.danger);
      return false;
    }
  }

  Future<bool> hasMicrophonePermission() => _recorder.hasPermission();

  Future<bool> stopAudio() async {
    String? audioPath;
    try {
      audioPath = await _recorder.stop();
    } catch (e) {
      pendingAudioPath = null;
      if (_disposed) return false;
      audioOnlyRecording = false;
      status = AppTexts.voiceAudioFailed;
      _changed();
      _host.notify(AppTexts.recordingFinishFailed(e), UiIntent.danger);
      return false;
    }
    if (_disposed) return false;
    if (audioPath == null || audioPath.isEmpty) {
      pendingAudioPath = null;
      audioOnlyRecording = false;
      status = AppTexts.voiceAudioNone;
      _changed();
      _host.notify(AppTexts.voiceAudioNoneHint, UiIntent.warning);
      return false;
    }
    pendingAudioPath = audioPath;
    audioOnlyRecording = false;
    status = AppTexts.voiceAudioReady;
    _changed();
    return true;
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}
