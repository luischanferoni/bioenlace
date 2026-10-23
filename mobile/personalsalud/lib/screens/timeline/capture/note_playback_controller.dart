import 'dart:async';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/foundation.dart';

/// Reproduce una nota de audio a la vez; al empezar otra, detiene la anterior.
class NotePlaybackController extends ChangeNotifier {
  AudioPlayer? _player;
  StreamSubscription<void>? _completeSub;
  String? _playingId;

  String? get playingId => _playingId;

  bool isPlaying(String id) => _playingId == id;

  Future<void> toggle(String id, String path) async {
    if (_playingId == id) {
      await stop();
      return;
    }
    final player = _player ??= AudioPlayer();
    _completeSub ??= player.onPlayerComplete.listen((_) {
      _playingId = null;
      notifyListeners();
    });
    await player.stop();
    _playingId = id;
    notifyListeners();
    try {
      await player.play(DeviceFileSource(path));
    } catch (_) {
      _playingId = null;
      notifyListeners();
      rethrow;
    }
  }

  Future<void> stop() async {
    await _player?.stop();
    if (_playingId != null) {
      _playingId = null;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _completeSub?.cancel();
    _player?.dispose();
    super.dispose();
  }
}
