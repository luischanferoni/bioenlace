import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../models/pending_encounter_capture.dart';

/// Persistencia local de capturas en web (SharedPreferences; sin dart:io).
///
/// El audio no se copia a FS: se reutiliza el path/blob URL que pase el caller.
class PendingEncounterCaptureStore {
  PendingEncounterCaptureStore._();
  static final PendingEncounterCaptureStore instance =
      PendingEncounterCaptureStore._();

  static const _webPrefsKey = 'pending_encounter_captures_v1';

  final List<PendingEncounterCapture> _items = [];
  bool _loaded = false;

  Future<void> _ensureLoaded() async {
    if (_loaded) return;
    _items.clear();
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_webPrefsKey);
      if (raw != null && raw.isNotEmpty) {
        final decoded = json.decode(raw);
        if (decoded is List) {
          for (final row in decoded) {
            if (row is Map) {
              final item = PendingEncounterCapture.fromJson(
                Map<String, dynamic>.from(row),
              );
              if (item.id.isNotEmpty) {
                _items.add(item);
              }
            }
          }
        }
      }
    } catch (e, st) {
      debugPrint('[PendingEncounterCaptureStore.web] load failed: $e\n$st');
    }
    _loaded = true;
  }

  Future<void> _persistIndex() async {
    final prefs = await SharedPreferences.getInstance();
    final payload = _items.map((e) => e.toJson()).toList();
    await prefs.setString(_webPrefsKey, json.encode(payload));
  }

  String newId() {
    final ts = DateTime.now().millisecondsSinceEpoch;
    final r = Random().nextInt(1 << 20);
    return 'cap_${ts}_$r';
  }

  Future<List<PendingEncounterCapture>> listAll() async {
    await _ensureLoaded();
    final copy = List<PendingEncounterCapture>.from(_items);
    copy.sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
    return copy;
  }

  Future<List<PendingEncounterCapture>> listForContext({
    required int personaId,
    required String parent,
    required int parentId,
  }) async {
    final all = await listAll();
    return all
        .where((e) =>
            e.personaId == personaId &&
            e.parent == parent &&
            e.parentId == parentId)
        .toList();
  }

  Future<PendingEncounterCapture?> getById(String id) async {
    await _ensureLoaded();
    for (final e in _items) {
      if (e.id == id) return e;
    }
    return null;
  }

  Future<String?> absoluteAudioPath(PendingEncounterCapture item) async {
    if (!item.hasAudio) return null;
    return item.audioFileName;
  }

  Future<String?> importAudioFile({
    required String captureId,
    required String sourcePath,
  }) async {
    if (sourcePath.isEmpty) return null;
    // En web no hay FS durable: se guarda la referencia tal cual.
    return sourcePath;
  }

  Future<PendingEncounterCapture> upsert(PendingEncounterCapture item) async {
    await _ensureLoaded();
    final idx = _items.indexWhere((e) => e.id == item.id);
    if (idx >= 0) {
      _items[idx] = item;
    } else {
      _items.add(item);
    }
    await _persistIndex();
    return item;
  }

  Future<void> delete(String id) async {
    await _ensureLoaded();
    _items.removeWhere((e) => e.id == id);
    await _persistIndex();
  }
}
