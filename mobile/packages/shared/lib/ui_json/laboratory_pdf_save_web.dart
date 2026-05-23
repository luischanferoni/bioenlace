// ignore: avoid_web_libraries_in_flutter
import 'dart:html' as html;
import 'dart:typed_data';

Future<void> saveLaboratoryPdfBytes(List<int> bytes, String filename) async {
  final name = filename.trim().isEmpty ? 'informe-laboratorio.pdf' : filename.trim();
  final blob = html.Blob(<Uint8List>[Uint8List.fromList(bytes)], 'application/pdf');
  final url = html.Url.createObjectUrlFromBlob(blob);
  final anchor = html.AnchorElement(href: url)
    ..download = name
    ..style.display = 'none';
  html.document.body?.children.add(anchor);
  anchor.click();
  anchor.remove();
  html.Url.revokeObjectUrl(url);
}
