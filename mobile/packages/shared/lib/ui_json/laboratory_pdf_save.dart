import 'laboratory_pdf_save_io.dart'
    if (dart.library.html) 'laboratory_pdf_save_web.dart' as impl;

/// Guarda o comparte bytes de un PDF según la plataforma (web: descarga; móvil: share sheet).
Future<void> saveLaboratoryPdfBytes(List<int> bytes, String filename) {
  return impl.saveLaboratoryPdfBytes(bytes, filename);
}
