/* Hello BroSeven! - grafik dashboard admin (SVG murni, tanpa library). */
(function () {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var dataEl = document.getElementById('dashData');
    if (!dataEl) { return; }

    var data;
    try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }

    function svgEl(tag, attrs, text) {
        var el = document.createElementNS(NS, tag);
        Object.keys(attrs || {}).forEach(function (k) { el.setAttribute(k, attrs[k]); });
        if (text !== undefined) { el.textContent = text; }
        return el;
    }

    function emptyState(container) {
        var box = document.createElement('div');
        box.className = 'hb-empty';
        var icon = document.createElement('i');
        icon.className = 'bi bi-bar-chart';
        var span = document.createElement('span');
        span.textContent = 'Belum ada data absensi pada periode ini';
        box.appendChild(icon);
        box.appendChild(span);
        container.appendChild(box);
    }

    function stackedBar(container, cfg) {
        var labels = cfg.labels || [];
        var series = cfg.series || [];
        var n = labels.length;

        var totals = labels.map(function (_, i) {
            return series.reduce(function (sum, s) { return sum + (Number(s.data[i]) || 0); }, 0);
        });
        var max = Math.max.apply(null, totals.concat([0]));
        if (!n || max === 0) { emptyState(container); return; }

        var W = 640, H = 280, padL = 38, padR = 10, padT = 12, padB = 34;
        var plotW = W - padL - padR, plotH = H - padT - padB;
        var step = Math.max(1, Math.ceil(max / 4));
        var top = step * 4;

        var svg = svgEl('svg', { viewBox: '0 0 ' + W + ' ' + H, 'class': 'hb-chart-svg', role: 'img', 'aria-label': 'Grafik kehadiran' });

        for (var i = 0; i <= 4; i++) {
            var y = padT + plotH - (plotH * i / 4);
            svg.appendChild(svgEl('line', { x1: padL, x2: W - padR, y1: y, y2: y, stroke: '#e6e6e6', 'stroke-width': 1 }));
            svg.appendChild(svgEl('text', { x: padL - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 11, fill: '#7a7a7a' }, String(step * i)));
        }

        var slot = plotW / n;
        var barW = Math.min(44, slot * 0.6);

        labels.forEach(function (label, idx) {
            var x = padL + slot * idx + (slot - barW) / 2;
            var yCursor = padT + plotH;

            series.forEach(function (s) {
                var v = Number(s.data[idx]) || 0;
                if (!v) { return; }
                var h = plotH * v / top;
                yCursor -= h;
                var rect = svgEl('rect', { x: x, y: yCursor, width: barW, height: h, fill: s.color, rx: 2 });
                rect.appendChild(svgEl('title', {}, label + ' - ' + s.label + ': ' + v));
                svg.appendChild(rect);
            });

            svg.appendChild(svgEl('text', {
                x: x + barW / 2, y: H - 12, 'text-anchor': 'middle', 'font-size': 11, fill: '#6b6b6b'
            }, label));
        });

        container.appendChild(svg);

        var legend = document.createElement('div');
        legend.className = 'hb-legend';
        series.forEach(function (s) {
            var item = document.createElement('span');
            var dot = document.createElement('i');
            dot.className = 'hb-dot';
            dot.style.background = s.color;
            item.appendChild(dot);
            item.appendChild(document.createTextNode(s.label));
            legend.appendChild(item);
        });
        container.appendChild(legend);
    }

    document.querySelectorAll('.hb-chart[data-chart]').forEach(function (el) {
        var cfg = data[el.getAttribute('data-chart')];
        if (cfg) { stackedBar(el, cfg); }
    });
})();
