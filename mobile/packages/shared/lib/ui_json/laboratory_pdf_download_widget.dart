import 'dart:io';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';

import '../config/api_config.dart';

/// Descarga PDF de laboratorio vía API autenticada (GET binario).
class LaboratoryPdfDownloadWidget extends StatefulWidget {
  const LaboratoryPdfDownloadWidget({
    super.key,
    required this.pdfPath,
    required this.filename,
    this.authToken,
    this.appClient = 'paciente-flutter',
  });

  final String pdfPath;
  final String filename;
  final String? authToken;
  final String appClient;

  @override
  State<LaboratoryPdfDownloadWidget> createState() => _LaboratoryPdfDownloadWidgetState();
}

class _LaboratoryPdfDownloadWidgetState extends State<LaboratoryPdfDownloadWidget> {
  bool _loading = false;

  String _resolveUrl(String path) {
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    final base = AppConfig.apiUrl;
    final uri = Uri.parse(base);
    if (path.startsWith('/api/v1')) {
      final port = uri.hasPort ? ':${uri.port}' : '';
      return '${uri.scheme}://${uri.host}$port$path';
    }
    final prefix = base.endsWith('/') ? base.substring(0, base.length - 1) : base;
    final p = path.startsWith('/') ? path : '/$path';
    return '$prefix$p';
  }

  Future<void> _download() async {
    if (widget.pdfPath.trim().isEmpty) {
      return;
    }
    setState(() => _loading = true);
    try {
      final url = _resolveUrl(widget.pdfPath);
      final headers = AppConfig.jsonHeaders(
        bearerToken: widget.authToken,
        appClient: widget.appClient,
      );
      headers.remove('Content-Type');

      final res = await http
          .get(Uri.parse(url), headers: headers)
          .timeout(const Duration(seconds: AppConfig.httpTimeoutSeconds));

      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('HTTP ${res.statusCode}');
      }

      final dir = await getTemporaryDirectory();
      final name = widget.filename.trim().isEmpty ? 'informe-laboratorio.pdf' : widget.filename.trim();
      final file = File('${dir.path}/$name');
      await file.writeAsBytes(res.bodyBytes);

      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('PDF guardado: ${file.path}')),
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('No se pudo descargar el PDF: $e')),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: FilledButton.icon(
        onPressed: _loading || widget.pdfPath.trim().isEmpty ? null : _download,
        icon: _loading
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.picture_as_pdf_outlined),
        label: Text(_loading ? 'Descargando…' : 'Descargar PDF'),
      ),
    );
  }
}
