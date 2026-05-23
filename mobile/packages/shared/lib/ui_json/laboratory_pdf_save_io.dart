import 'dart:typed_data';

import 'package:share_plus/share_plus.dart';

Future<void> saveLaboratoryPdfBytes(List<int> bytes, String filename) async {
  final name = filename.trim().isEmpty ? 'informe-laboratorio.pdf' : filename.trim();
  await Share.shareXFiles(
    [
      XFile.fromData(
        Uint8List.fromList(bytes),
        name: name,
        mimeType: 'application/pdf',
      ),
    ],
    subject: name,
    text: 'Informe de laboratorio',
  );
}
