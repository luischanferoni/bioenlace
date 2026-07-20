import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:cross_file/cross_file.dart';

/// Parte `file` de un multipart desde [XFile] (bytes en web, path en móvil/desktop).
Future<http.MultipartFile> multipartFileFromXFile(
  XFile xFile, {
  String field = 'file',
}) async {
  final name = xFile.name.trim();
  final fromPath = xFile.path.split(RegExp(r'[/\\]')).where((s) => s.isNotEmpty).last;
  final filename = name.isNotEmpty
      ? name
      : (fromPath.isNotEmpty ? fromPath : 'upload.bin');

  if (kIsWeb) {
    final bytes = await xFile.readAsBytes();
    return http.MultipartFile.fromBytes(field, bytes, filename: filename);
  }

  return http.MultipartFile.fromPath(field, xFile.path, filename: filename);
}

Future<http.MultipartFile> multipartFileFromBytes(
  List<int> bytes, {
  required String filename,
  String field = 'file',
}) async {
  return http.MultipartFile.fromBytes(field, bytes, filename: filename);
}
