import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/screens/timeline/capture/capture_dictation_controller.dart';
import 'package:shared/shared.dart';

void main() {
  late List<String> messages;
  late String text;
  late CaptureDictationController controller;
  var busy = false;

  setUp(() {
    messages = [];
    text = '';
    busy = false;
    controller = CaptureDictationController(
      CaptureDictationHost(
        getText: () => text,
        setText: (v) => text = v,
        notify: (m, _) => messages.add(m),
        showPermissionDialog: ({bool permissionRequestFailed = false}) async {},
        saveDraft: (_) async {},
        isBusy: () => busy,
        hasCaptureContext: () => true,
      ),
    );
  });

  tearDown(() => controller.dispose());

  test('avisa si la configuración de voz aún no cargó', () async {
    await controller.toggle();
    expect(messages, hasLength(1));
    expect(controller.recording, isFalse);
  });

  test('no hace nada si la captura está ocupada', () async {
    busy = true;
    await controller.toggle();
    expect(messages, isEmpty);
  });

  test('con la voz deshabilitada avisa y no graba', () async {
    controller.applyConfig(SttClientConfig.unavailable);
    expect(controller.voiceAvailable, isFalse);
    await controller.toggle();
    expect(messages, hasLength(1));
    expect(controller.recording, isFalse);
  });

  test('applyConfig habilita la voz y notifica a la pantalla', () {
    var notified = 0;
    controller.addListener(() => notified++);
    controller.applyConfig(SttClientConfig.defaults);
    expect(controller.configLoaded, isTrue);
    expect(notified, 1);
  });

  test('reset limpia audio, dictado y estado', () {
    controller.pendingAudioPath = 'x.m4a';
    controller.status = 'Listo';
    controller.reset();
    expect(controller.pendingAudioPath, isNull);
    expect(controller.status, '');
  });
}
