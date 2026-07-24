import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';

import '../models/pending_encounter_capture.dart';

/// Persistencia local de capturas (móvil/desktop): índice + audio en documentos.
class PendingEncounterCaptureStore {
  PendingEncounterCaptureStore._();
  static final PendingEncounterCaptureStore instance =
      PendingEncounterCaptureStore._();

  static const _dirName = 'pending-encounters';
  static const _indexFile = 'index.json';

  Directory? _root;
  final List<PendingEncounterCapture> _items = [];
  bool _loaded = false;

  Future<Directory> _ensureRoot() async {
    if (_root != null) return _root!;
    final docs = await getApplicationDocumentsDirectory();
    final dir = Directory('${docs.path}/$_dirName');
    if (!await dir.exists()) {
      await dir.create(recursive: true);
    }
    _root = dir;
    return dir;
  }

  Future<File> _indexPath() async {
    final root = await _ensureRoot();
    return File('${root.path}/$_indexFile');
  }

  Future<void> _ensureLoaded() async {
    if (_loaded) return;
    final file = await _indexPath();
    _items.clear();
    if (await file.exists()) {
      try {
        final raw = json.decode(await file.readAsString());
        if (raw is List) {
          for (final row in raw) {
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
      } catch (e, st) {
        debugPrint('[PendingEncounterCaptureStore] index corrupt: $e\n$st');
      }
    }
    _loaded = true;
  }

  Future<void> _persistIndex() async {
    final file = await _indexPath();
    final payload = _items.map((e) => e.toJson()).toList();
    await file.writeAsString(const JsonEncoder.withIndent('  ').convert(payload));
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
    final root = await _ensureRoot();
    final path = '${root.path}/${item.audioFileName}';
    if (await File(path).exists()) return path;
    return null;
  }

  Future<String?> importAudioFile({
    required String captureId,
    required String sourcePath,
  }) async {
    try {
      final src = File(sourcePath);
      if (!await src.exists()) return null;
      final root = await _ensureRoot();
      final name = '$captureId.m4a';
      final dest = File('${root.path}/$name');
      await src.copy(dest.path);
      return name;
    } catch (e, st) {
      debugPrint('[PendingEncounterCaptureStore] importAudio failed: $e\n$st');
      return null;
    }
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
    final existing = await getById(id);
    _items.removeWhere((e) => e.id == id);
    await _persistIndex();
    if (existing?.audioFileName != null) {
      final root = await _ensureRoot();
      final audio = File('${root.path}/${existing!.audioFileName}');
      if (await audio.exists()) {
        try {
          await audio.delete();
        } catch (_) {}
      }
    }
  }
}
