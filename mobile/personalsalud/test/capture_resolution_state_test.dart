import 'package:flutter_test/flutter_test.dart';
import 'package:personalsalud/screens/timeline/capture/capture_resolution_state.dart';

void main() {
  test('desmarcar un ítem limpia sus resoluciones y textos', () {
    final s = CaptureResolutionState();
    s.toggleStagedItem('a', true);
    s.issueResolutions['a:1'] = 'x';
    s.issueResolutions['b:1'] = 'y';
    s.customController('a:1').text = 'libre';
    s.toggleStagedItem('a', false);
    expect(s.stagedItemIds, isEmpty);
    expect(s.issueResolutions.containsKey('a:1'), isFalse);
    expect(s.issueResolutions['b:1'], 'y');
    expect(s.customText('a:1'), '');
    s.dispose();
  });

  test('backup y restore conservan el estado', () {
    final s = CaptureResolutionState();
    s.toggleStagedItem('a', true);
    s.selectIssueOption('i', 1, false);
    s.selectOpenProblemOption('condition:1', 2, false);
    s.backupAndReset();
    expect(s.stagedItemIds, isEmpty);
    expect(s.issueResolutions, isEmpty);
    s.restoreBackup();
    expect(s.stagedItemIds, {'a'});
    expect(s.issueResolutions['i'], 1);
    expect(s.openProblemResolutions['condition:1'], 2);
    s.dispose();
  });

  test('seleccionar de nuevo una opción la deselecciona', () {
    final s = CaptureResolutionState();
    s.selectIssueOption('i', 1, false);
    s.selectIssueOption('i', 1, true);
    expect(s.issueResolutions, isEmpty);
    s.dispose();
  });
}
