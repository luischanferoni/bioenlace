import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:path_provider/path_provider.dart';

import '../models/pending_motivo_message.dart';

/// Persistencia local de motivos pendientes (texto + audio).
///
/// ```
/// <appDoc>/pending-motivos/
///   index.json
///   <id>.m4a
/// ```
class PendingMotivoMessageStore {
  PendingMotivoMessageStore._();
  static final PendingMotivoMessageStore instance =
      PendingMotivoMessageStore._();

  static const _dirName = 'pending-motivos';
  static const _indexFile = 'index.json';

  Directory? _root;
  final List<PendingMotivoMessage> _items = [];
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
              final item = PendingMotivoMessage.fromJson(
                Map<String, dynamic>.from(row),
              );
              if (item.id.isNotEmpty) {
                _items.add(item);
              }
            }
          }
        }
      } catch (_) {}
    }
    _loaded = true;
  }

  Future<void> _persistIndex() async {
    final file = await _indexPath();
    final payload = _items.map((e) => e.toJson()).toList();
    await file.writeAsString(
      const JsonEncoder.withIndent('  ').convert(payload),
    );
  }

  String newId() {
    final ts = DateTime.now().millisecondsSinceEpoch;
    final r = Random().nextInt(1 << 20);
    return 'mot_${ts}_$r';
  }

  Future<List<PendingMotivoMessage>> listForConsulta(int consultaId) async {
    await _ensureLoaded();
    final copy = _items.where((e) => e.consultaId == consultaId).toList();
    copy.sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
    return copy;
  }

  Future<PendingMotivoMessage?> getById(String id) async {
    await _ensureLoaded();
    for (final e in _items) {
      if (e.id == id) return e;
    }
    return null;
  }

  Future<String?> absoluteAudioPath(PendingMotivoMessage item) async {
    if (!item.hasAudio) return null;
    final root = await _ensureRoot();
    final path = '${root.path}/${item.audioFileName}';
    if (await File(path).exists()) return path;
    return null;
  }

  Future<String?> importAudioFile({
    required String messageId,
    required String sourcePath,
  }) async {
    final src = File(sourcePath);
    if (!await src.exists()) return null;
    final root = await _ensureRoot();
    final name = '$messageId.m4a';
    final dest = File('${root.path}/$name');
    await src.copy(dest.path);
    return name;
  }

  Future<PendingMotivoMessage> upsert(PendingMotivoMessage item) async {
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
