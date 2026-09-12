/**
 * Curvas de crecimiento (Plotly). Config: #curvas-crecimiento-root[data-curvas-config]
 */
(function () {
  'use strict';

  var root = document.getElementById('curvas-crecimiento-root');
  if (!root || typeof Plotly === 'undefined') {
    return;
  }
  var cfg = {};
  try {
    cfg = JSON.parse(root.getAttribute('data-curvas-config') || '{}');
  } catch (e) {
    console.error('curvas config inválida', e);
    return;
  }
  (cfg.charts || []).forEach(function (chart) {
    if (!chart || !chart.elementId) {
      return;
    }
    var el = document.getElementById(chart.elementId);
    if (!el) {
      return;
    }
    var layout = {
      title: chart.title || '',
      displayModeBar: false,
      xaxis: { title: 'Edad' },
      yaxis: { title: chart.yTitle || '' },
      legend: { traceorder: 'reversed' },
    };
    Plotly.newPlot(chart.elementId, chart.traces || [], layout);
  });
})();
