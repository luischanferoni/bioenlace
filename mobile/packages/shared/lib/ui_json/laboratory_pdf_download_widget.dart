import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import 'laboratory_pdf_save.dart';
import 'ui_json_screen.dart';

/// Descarga PDF de laboratorio vía API autenticada.
/// Web (Chrome): descarga directa; móvil: hoja de compartir del SO.
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

  Future<void> _download() async {
    if (widget.pdfPath.trim().isEmpty) {
      return;
    }
    setState(() => _loading = true);
    try {
      final url = resolveApiAbsoluteUrl(widget.pdfPath);
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

      final name =
          widget.filename.trim().isEmpty ? 'informe-laboratorio.pdf' : widget.filename.trim();
      await saveLaboratoryPdfBytes(res.bodyBytes, name);

      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('PDF listo: $name')),
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
