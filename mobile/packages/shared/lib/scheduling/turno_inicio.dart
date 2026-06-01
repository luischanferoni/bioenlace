import 'package:timezone/data/latest.dart' as tz_data;
import 'package:timezone/timezone.dart' as tz;

/// Zona horaria del backend Yii (`web/frontend/config/main.php` → `timeZone`).
const String kProductTimeZoneId = 'America/Argentina/Tucuman';

bool _tzReady = false;

void _ensureTz() {
  if (_tzReady) return;
  tz_data.initializeTimeZones();
  _tzReady = true;
}

tz.Location get productTimeZone {
  _ensureTz();
  return tz.getLocation(kProductTimeZoneId);
}

/// Interpreta `fecha` + `hora` del turno como reloj de producto (no del dispositivo).
DateTime? parseTurnoInicioProducto(Map<String, dynamic> turno) {
  final fechaRaw = turno['fecha'];
  String fechaStr;
  if (fechaRaw is String) {
    fechaStr = fechaRaw.trim().split('T').first;
  } else if (fechaRaw is DateTime) {
    fechaStr =
        '${fechaRaw.year.toString().padLeft(4, '0')}-${fechaRaw.month.toString().padLeft(2, '0')}-${fechaRaw.day.toString().padLeft(2, '0')}';
  } else {
    return null;
  }
  if (fechaStr.length < 10) return null;

  final horaRaw = turno['hora'];
  String horaNorm;
  if (horaRaw is String && horaRaw.trim().isNotEmpty) {
    horaNorm = horaRaw.trim();
  } else {
    horaNorm = '00:00:00';
  }
  if (horaNorm.length == 5 && horaNorm.contains(':')) {
    horaNorm = '$horaNorm:00';
  }

  final parts = fechaStr.split('-');
  if (parts.length != 3) return null;
  final y = int.tryParse(parts[0]);
  final m = int.tryParse(parts[1]);
  final d = int.tryParse(parts[2]);
  if (y == null || m == null || d == null) return null;

  final hm = horaNorm.split(':');
  if (hm.isEmpty) return null;
  final hh = int.tryParse(hm[0]) ?? 0;
  final mm = hm.length > 1 ? (int.tryParse(hm[1]) ?? 0) : 0;
  final ss = hm.length > 2 ? (int.tryParse(hm[2]) ?? 0) : 0;

  final loc = productTimeZone;
  final tzDt = tz.TZDateTime(loc, y, m, d, hh, mm, ss);
  return DateTime.fromMillisecondsSinceEpoch(tzDt.millisecondsSinceEpoch);
}

DateTime nowProducto() {
  final loc = productTimeZone;
  final tzNow = tz.TZDateTime.now(loc);
  return DateTime.fromMillisecondsSinceEpoch(tzNow.millisecondsSinceEpoch);
}

bool turnoInicioEsProximoEnProducto(Map<String, dynamic> turno) {
  final inicio = parseTurnoInicioProducto(turno);
  if (inicio == null) return true;
  return !inicio.isBefore(nowProducto());
}

bool turnoInicioEsPasadoEnProducto(Map<String, dynamic> turno) {
  final inicio = parseTurnoInicioProducto(turno);
  if (inicio == null) return false;
  return inicio.isBefore(nowProducto());
}

/// Ventana de motivos de consulta: abierta hasta [minutosAntesCierre] antes del inicio del turno.
bool turnoMotivosInputAbiertoEnProducto(
  Map<String, dynamic> turno, {
  int minutosAntesCierre = 2,
}) {
  final flag = turno['motivos_input_abierto'];
  if (flag is bool) return flag;

  final inicio = parseTurnoInicioProducto(turno);
  if (inicio == null) return false;

  final minsRaw = turno['motivos_cierre_minutos'];
  final mins = minsRaw is int
      ? minsRaw
      : (minsRaw != null ? int.tryParse(minsRaw.toString()) : null) ??
          minutosAntesCierre;

  final cierre = inicio.subtract(Duration(minutes: mins));
  return nowProducto().isBefore(cierre);
}

bool turnoTieneEncounterParaMotivos(Map<String, dynamic> turno) {
  final raw = turno['encounter_id'] ?? turno['id_consulta'];
  if (raw is int) return raw > 0;
  if (raw == null) return false;
  final id = int.tryParse(raw.toString());
  return id != null && id > 0;
}
