/* ================================================
   Stock Chart Plugin — fixed JS v1.1
   ================================================ */
'use strict';

// Fixed month abbreviations — avoids en-IN locale inconsistencies across browsers
var JSL_MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

var jslChart       = null;
var jslCurrentData = {};
var jslSymbol      = 'JSL';
var jslExchange    = 'NSE';
var jslPeriod      = '1Y';
var _jslLastPrice  = ''; // tracks last rendered price for rolling animation

// ── Init JSL chart ──
function jslStockChartInit(symbol, exchange) {
    jslSymbol   = symbol;
    jslExchange = exchange;

    var canvas = document.getElementById('jsl-stock-chart-canvas');
    if (!canvas) return;

    var ctx      = canvas.getContext('2d');
    var gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(251,146,60,0.15)');
    gradient.addColorStop(1, 'rgba(251,146,60,0.01)');

    jslChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Stock Price',
                data: [],
                borderColor: '#fb923c',
                backgroundColor: gradient,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#fb923c',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            // Exclude wheel so scroll events pass through to Lenis / native scroll
            events: ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'],
            interaction: { intersect: false, mode: 'index' },
            layout: {
                padding: { left: 0, right: 0, top: 8, bottom: 0 }
            },
            animation: {
                duration: 700,
                easing: 'easeInOutQuart'
            },
            transitions: {
                active: { animation: { duration: 200 } }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.85)',
                    titleColor: '#9ca3af',
                    bodyColor: '#fff',
                    footerColor: '#9ca3af',
                    borderColor: '#fb923c',
                    borderWidth: 1,
                    cornerRadius: 6,
                    displayColors: true,
                    usePointStyle: true,
                    boxWidth: 8,
                    boxHeight: 8,
                    padding: 10,
                    footerFont: { size: 11 },
                    callbacks: {
                        title: function () { return null; }, // null = hide title line entirely
                        label: function (ctx) {
                            return ' ' + jslSymbol + ' (' + jslExchange + ')';
                        },
                        afterLabel: function (ctx) {
                            return '  ₹ ' + ctx.parsed.y.toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        },
                        footer: function (tooltipItems) {
                            var idx = tooltipItems[0].dataIndex;
                            if (jslCurrentData && jslCurrentData.data && jslCurrentData.data[idx]) {
                                var d   = new Date(jslCurrentData.data[idx].date);
                                var day = d.getDay(); // 0=Sun
                                var DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                                if (jslCurrentData.period === '1D') {
                                    return d.toLocaleTimeString('en-IN', {
                                        hour: '2-digit', minute: '2-digit',
                                        timeZone: 'Asia/Kolkata'
                                    });
                                }
                                // "Friday, 12 Jun 2026" — no locale dependency
                                return DAYS[d.getDay()] + ', ' + d.getDate() + ' ' +
                                       JSL_MONTHS[d.getMonth()] + ' ' + d.getFullYear();
                            }
                            return tooltipItems[0].label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    offset: false,
                    bounds: 'ticks',
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: '#6b7280',
                        font: { size: 11 },
                        maxTicksLimit: 7,
                        maxRotation: 0,
                        minRotation: 0,
                        align: 'center',
                        padding: 6
                    }
                },
                y: {
                    display: true,
                    position: 'right',
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        color: '#6b7280',
                        font: { size: 11 },
                        callback: function (val) {
                            return val.toFixed(0);
                        }
                    }
                }
            }
        }
    });

    // ── Period buttons ──
    var periodBtns = document.querySelectorAll('#jsl-stock-chart-container .stock-period-btn');
    periodBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var period = this.getAttribute('data-period');

            if (period === 'Custom') {
                jslToggleCustomDatePicker(true);
                return;
            }

            jslToggleCustomDatePicker(false);
            periodBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            jslPeriod = period;
            jslLoadData(jslSymbol, jslExchange, period, '', '');
        });
    });

    // ── Exchange radio buttons ──
    var radioInputs = document.querySelectorAll('input[name="jsl_stock_exchange"]');
    radioInputs.forEach(function (radio) {
        radio.addEventListener('change', function () {
            jslExchange = this.value;
            jslLoadData(jslSymbol, jslExchange, jslPeriod, '', '');
        });
    });

    // ── Custom date apply button ──
    var applyBtn = document.getElementById('jsl-custom-date-apply');
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            var from = document.getElementById('jsl-date-from').value;
            var to   = document.getElementById('jsl-date-to').value;

            if (!from || !to) {
                alert('Please select both start and end dates.');
                return;
            }
            if (new Date(from) > new Date(to)) {
                alert('Start date must be before end date.');
                return;
            }

            // Mark calendar button active
            var periodBtns = document.querySelectorAll('#jsl-stock-chart-container .stock-period-btn');
            periodBtns.forEach(function (b) { b.classList.remove('active'); });
            document.getElementById('jsl-custom-btn').classList.add('active');

            jslPeriod = 'Custom';
            jslLoadData(jslSymbol, jslExchange, 'Custom', from, to);
        });
    }

    // Load default period on init
    jslLoadData(jslSymbol, jslExchange, jslPeriod, '', '');
}

function jslToggleCustomDatePicker(show) {
    var picker = document.getElementById('jsl-custom-date-range');
    if (picker) picker.style.display = show ? 'flex' : 'none';
}

function jslShowError(msg) {
    var el = document.getElementById('jsl-chart-error');
    if (!el) return;
    el.textContent = msg;
    el.style.display = 'block';
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(function () { el.style.display = 'none'; }, 5000);
}

// AbortController so switching periods cancels any in-flight request
var _jslCurrentAbort = null;

// ── Load data via AJAX ──
function jslLoadData(symbol, exchange, period, startDate, endDate) {
    // Use server-inlined data for the first default load — no AJAX needed
    if (window.jslInlineStockData && period === '1Y' && !startDate && !endDate) {
        var inlined = window.jslInlineStockData;
        window.jslInlineStockData = null; // consume once
        jslUpdateChart(inlined);
        jslUpdatePriceDisplay(inlined);
        jslCurrentData = inlined;
        return;
    }

    // Cancel any previous in-flight request to prevent race conditions
    if (_jslCurrentAbort) { _jslCurrentAbort.abort(); }
    var abortCtrl = new AbortController();
    _jslCurrentAbort = abortCtrl;

    var loading = document.getElementById('jsl-stock-chart-loading');
    var canvas  = document.getElementById('jsl-stock-chart-canvas');
    var errEl   = document.getElementById('jsl-chart-error');

    if (loading) loading.style.display = 'flex';
    if (canvas)  canvas.style.opacity  = '0.5';
    if (errEl)   errEl.style.display   = 'none';

    var formData = new FormData();
    formData.append('action',     'get_stock_data');
    formData.append('symbol',     symbol);
    formData.append('exchange',   exchange);
    formData.append('period',     period);
    formData.append('nonce',      stockChartAjax.nonce);
    if (startDate) formData.append('start_date', startDate);
    if (endDate)   formData.append('end_date',   endDate);

    fetch(stockChartAjax.ajax_url, { method: 'POST', body: formData, signal: abortCtrl.signal })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                jslUpdateChart(result.data);
                jslUpdatePriceDisplay(result.data);
                jslCurrentData = result.data;
                // Close the custom picker once data loads so the chart is fully visible
                if (period === 'Custom') { jslToggleCustomDatePicker(false); }
            } else {
                var msg = (result.data && result.data.message) ? result.data.message : 'Unable to fetch data.';
                console.error('Stock data error:', msg);
                jslShowError(msg);
            }
        })
        .catch(function (err) {
            if (err.name === 'AbortError') return; // intentionally cancelled — no error display
            console.error('Stock fetch error:', err);
            jslShowError('Network error. Please try again.');
        })
        .finally(function () {
            if (loading) loading.style.display = 'none';
            if (canvas)  canvas.style.opacity  = '1';
        });
}

// ── Update chart ──
function jslUpdateChart(data) {
    if (!jslChart || !data.data) return;

    var labels = data.data.map(function (item) {
        var d = new Date(item.date);
        if (data.period === '1D') {
            // "09:30 AM" IST — locale-safe time string
            return d.toLocaleTimeString('en-IN', {
                hour: '2-digit', minute: '2-digit',
                timeZone: 'Asia/Kolkata'
            });
        }
        if (data.period === '1Y' || data.period === '2Y') {
            // "Jul '25" — explicit array, no locale dependency
            return JSL_MONTHS[d.getMonth()] + " '" + String(d.getFullYear()).slice(-2);
        }
        // 1M, 3M, 6M, Custom → "13 Apr" — explicit array
        return d.getDate() + ' ' + JSL_MONTHS[d.getMonth()];
    });

    var prices = data.data.map(function (item) { return item.close; });

    jslChart.data.labels             = labels;
    jslChart.data.datasets[0].data   = prices;
    jslChart.update();
}

// ── Rolling odometer animation for price digits ──
function jslBuildStaticPrice(str) {
    var html = '<span class="jsl-price-num">';
    for (var i = 0; i < str.length; i++) {
        var c = str[i];
        if (c === '.') {
            html += '<span class="jsl-sep">.</span>';
        } else {
            html += '<span class="jsl-digit-slot"><span class="jsl-digit-strip"><span>' + c + '</span></span></span>';
        }
    }
    return html + '</span>';
}

function jslAnimatePrice(priceEl, newStr, isPositive) {
    if (!priceEl) return;

    var oldStr = _jslLastPrice;
    if (oldStr === newStr) return; // nothing changed
    _jslLastPrice = newStr;

    // First render — no animation, just build structure
    if (!oldStr) {
        priceEl.innerHTML = jslBuildStaticPrice(newStr);
        return;
    }

    // Align character positions from the left, padding shorter string with spaces
    var maxLen = Math.max(newStr.length, oldStr.length);
    var pNew   = newStr.padStart(maxLen, ' ');
    var pOld   = oldStr.padStart(maxLen, ' ');

    var container = document.createElement('span');
    container.className = 'jsl-price-num';

    var toAnimate = []; // strips that need a CSS transition

    for (var i = 0; i < maxLen; i++) {
        var nc = pNew[i]; // new char
        var oc = pOld[i]; // old char

        if (nc === ' ' && oc === ' ') continue;

        if (nc === '.') {
            var sep = document.createElement('span');
            sep.className = 'jsl-sep';
            sep.textContent = '.';
            container.appendChild(sep);
            continue;
        }

        var slot = document.createElement('span');
        slot.className = 'jsl-digit-slot';

        var strip = document.createElement('span');
        strip.className = 'jsl-digit-strip';

        var same = (nc === oc || nc === ' ' || oc === ' ');

        if (same) {
            // No animation — just show the visible character
            var staticSp = document.createElement('span');
            staticSp.textContent = nc !== ' ' ? nc : oc;
            strip.appendChild(staticSp);
        } else if (isPositive) {
            // Price went UP → strip rolls upward: old visible → new rolls in from below
            // Strip: [old (visible at 0), new (hidden below)]
            // Animate: translateY(0) → translateY(-50%)
            strip.innerHTML = '<span>' + oc + '</span><span>' + nc + '</span>';
            strip.style.transform = 'translateY(0)';
            toAnimate.push({ el: strip, to: 'translateY(-50%)' });
        } else {
            // Price went DOWN → strip rolls downward: new rolls in from above
            // Strip: [new (hidden above), old (visible at -50%)]
            // Animate: translateY(-50%) → translateY(0)
            strip.innerHTML = '<span>' + nc + '</span><span>' + oc + '</span>';
            strip.style.transform = 'translateY(-50%)';
            toAnimate.push({ el: strip, to: 'translateY(0)' });
        }

        slot.appendChild(strip);
        container.appendChild(slot);
    }

    priceEl.innerHTML = '';
    priceEl.appendChild(container);

    if (toAnimate.length === 0) return;

    // Double rAF: first lets browser commit initial DOM state,
    // second fires just before next paint so transition sees the delta.
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            var total = toAnimate.length;
            toAnimate.forEach(function (item, index) {
                // Stagger right-to-left: rightmost digit (last in array) starts first
                var delay = (total - 1 - index) * 45;
                item.el.style.transition = 'transform 0.48s cubic-bezier(0.23, 1, 0.32, 1) ' + delay + 'ms';
                item.el.style.transform  = item.to;
            });
        });
    });
}

// ── Update price display ──
function jslUpdatePriceDisplay(data) {
    var priceEl   = document.getElementById('jsl-stock-price');
    var changeEl  = document.getElementById('jsl-stock-change');
    var percentEl = document.getElementById('jsl-stock-change-percent');

    var absChange  = parseFloat(data.change_abs || 0);
    var pctChange  = parseFloat(data.change     || 0);
    var isPositive = absChange >= 0;

    if (priceEl) {
        jslAnimatePrice(priceEl, parseFloat(data.current_price).toFixed(2), isPositive);
    }

    if (changeEl) {
        // Apply class directly to the element — theme CSS colors .stock-price-change.negative red
        // and uses ::before for the ↑/↓ arrow direction
        changeEl.className   = 'stock-price-change' + (isPositive ? '' : ' negative');
        changeEl.textContent = Math.abs(absChange).toFixed(2);
    }
    if (percentEl) {
        percentEl.className   = 'stock-price-change-percent' + (isPositive ? '' : ' negative');
        percentEl.textContent = '(' + (isPositive ? '+' : '-') + Math.abs(pctChange).toFixed(2) + '%)';
    }

    // Update date — "Tuesday, 30 Jun 2026" — no locale dependency
    var dateEl = document.getElementById('jsl-stock-date');
    if (dateEl && data.data && data.data.length > 0) {
        var DAYS_FULL = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var lastDate  = new Date(data.data[data.data.length - 1].date);
        dateEl.textContent = DAYS_FULL[lastDate.getDay()] + ', ' +
            lastDate.getDate() + ' ' + JSL_MONTHS[lastDate.getMonth()] + ' ' + lastDate.getFullYear();
    }

    // Update market open/closed status based on IST 9:15 AM – 3:30 PM Mon–Fri
    var statusEl = document.getElementById('jsl-stock-status');
    if (statusEl) {
        var now = new Date();
        var utcMs  = now.getTime() + now.getTimezoneOffset() * 60000;
        var istDate = new Date(utcMs + 330 * 60000); // IST = UTC+5:30
        var h    = istDate.getHours();
        var m    = istDate.getMinutes();
        var day  = istDate.getDay(); // 0=Sun, 6=Sat
        var mins = h * 60 + m;
        var isWeekday = day >= 1 && day <= 5;
        var isOpen    = isWeekday && mins >= (9 * 60 + 15) && mins <= (15 * 60 + 30);
        statusEl.textContent = isOpen ? 'Open' : 'Closed';
        statusEl.className   = 'market-status ' + (isOpen ? 'market-open' : 'market-closed');
    }
}

// ── Legacy functions kept for chart-template.php compatibility ──
function initStockChart(containerId, symbol, exchange) {
    var ctx      = document.getElementById('chart-' + containerId).getContext('2d');
    var gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(251,146,60,0.1)');
    gradient.addColorStop(1, 'rgba(251,146,60,0.01)');

    stockCharts[containerId] = new Chart(ctx, {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Stock Price', data: [], borderColor: '#fb923c', backgroundColor: gradient, borderWidth: 2, fill: true, tension: 0.4, pointRadius: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: true, grid: { display: false } }, y: { display: false } } }
    });

    loadStockData(containerId, symbol, exchange, '1M');
}

var stockCharts = {};

function loadStockData(containerId, symbol, exchange, period) {
    var loading = document.getElementById('loading-' + containerId);
    var chart   = document.getElementById('chart-' + containerId);
    if (loading) loading.style.display = 'flex';
    if (chart)   chart.style.opacity   = '0.5';

    var fd = new FormData();
    fd.append('action', 'get_stock_data');
    fd.append('symbol', symbol);
    fd.append('exchange', exchange);
    fd.append('period', period);
    fd.append('nonce', stockChartAjax.nonce);

    fetch(stockChartAjax.ajax_url, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                updateChartData(containerId, result.data);
                updatePriceInfo(containerId, result.data);
            }
        })
        .finally(function () {
            if (loading) loading.style.display = 'none';
            if (chart)   chart.style.opacity   = '1';
        });
}

function updateChartData(containerId, data) {
    var chart = stockCharts[containerId];
    if (!chart || !data.data) return;
    chart.data.labels           = data.data.map(function (i) { return new Date(i.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }); });
    chart.data.datasets[0].data = data.data.map(function (i) { return i.close; });
    chart.update('none');
}

function updatePriceInfo(containerId, data) {
    var p = document.getElementById('current-price-' + containerId);
    var c = document.getElementById('price-change-' + containerId);
    if (p) p.textContent = parseFloat(data.current_price).toFixed(2);
    if (c) {
        c.textContent = (data.change >= 0 ? '+' : '') + parseFloat(data.change).toFixed(2) + '%';
        c.parentElement.className = 'price-change ' + (data.change >= 0 ? 'positive' : 'negative');
    }
}

function switchExchange(containerId, exchange) {
    var container   = document.getElementById(containerId);
    var activeFilter = container.querySelector('.time-filter.active');
    container.querySelectorAll('.exchange-tab').forEach(function (t) {
        t.classList.toggle('active', t.dataset.exchange === exchange);
    });
    var period = activeFilter ? activeFilter.dataset.period : '1M';
    loadStockData(containerId, stockCharts[containerId] ? containerId : containerId, exchange, period);
}

function updateChart(containerId, period) {
    var container = document.getElementById(containerId);
    var activeTab = container.querySelector('.exchange-tab.active');
    container.querySelectorAll('.time-filter').forEach(function (f) {
        f.classList.toggle('active', f.dataset.period === period);
    });
    var exchange = activeTab ? activeTab.dataset.exchange : 'NSE';
    loadStockData(containerId, containerId, exchange, period);
}

// ── Auto-init JSL chart from data attributes on the container ──
// Runs here (inside stock-chart.js footer script) so it works even when a
// caching/optimisation plugin defers inline scripts and DOMContentLoaded
// has already fired by the time the template's inline code would have run.
function jslAutoInit() {
    var container = document.getElementById('jsl-stock-chart-container');
    if (!container) return;

    var symbol   = container.getAttribute('data-symbol')   || 'JSL';
    var exchange = container.getAttribute('data-exchange')  || 'NSE';

    function doInit() {
        jslStockChartInit(symbol, exchange);
    }

    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries, observer) {
            if (entries[0].isIntersecting) {
                observer.disconnect();
                doInit();
            }
        }, { rootMargin: '150px 0px' });
        io.observe(container);
    } else {
        doInit();
    }
}

// Handle both: DOMContentLoaded not yet fired (normal) and already fired (deferred scripts)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', jslAutoInit);
} else {
    jslAutoInit();
}
