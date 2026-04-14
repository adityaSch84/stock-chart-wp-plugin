/**
 * JSL-style stock chart front-end (bundled with stock-chart-plugin).
 * Uses stockChartAjax from wp_localize_script: ajax_url, nonce, symbol, default_exchange.
 */
(function () {
    'use strict';

    let jslStockChart = null;
    let currentExchange = 'NSE';
    let currentPeriod = '1Y';
    let symbol = 'JSL';

    function readLocalizedConfig() {
        var container = document.getElementById('jsl-stock-chart-container');
        var fromData = container ? container.getAttribute('data-stock-symbol') : '';
        symbol = (stockChartAjax.symbol || fromData || 'JSL').toString().trim() || 'JSL';
        var defEx = (stockChartAjax.default_exchange || 'NSE').toString().toUpperCase();
        if (defEx !== 'BSE') {
            defEx = 'NSE';
        }
        return { defaultExchange: defEx };
    }

    function initJSLStockChart() {
        if (typeof Chart === 'undefined') {
            setTimeout(initJSLStockChart, 100);
            return;
        }
        if (typeof stockChartAjax === 'undefined') {
            setTimeout(initJSLStockChart, 100);
            return;
        }

        var canvas = document.getElementById('jsl-stock-chart-canvas');
        if (!canvas) {
            return;
        }

        var cfg = readLocalizedConfig();
        var checked = document.querySelector('input[name="stock_exchange"]:checked');
        currentExchange = checked ? checked.value : cfg.defaultExchange;

        var ctx = canvas.getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(239, 127, 26, 0.1)');
        gradient.addColorStop(1, 'rgba(239, 127, 26, 0.01)');

        jslStockChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Stock Price',
                    data: [],
                    borderColor: '#ef7f1a',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    spanGaps: true,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#ef7f1a',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                layout: {
                    padding: {
                        top: 8,
                        right: 8,
                        bottom: 4,
                        left: 4
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#ef7f1a',
                        borderWidth: 1,
                        cornerRadius: 6,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                return '₹ ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: { display: false },
                        ticks: {
                            color: '#6b7280',
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 14,
                            font: { size: 11 }
                        }
                    },
                    y: {
                        display: true,
                        position: 'right',
                        grace: '5%',
                        grid: {
                            display: true,
                            color: '#f3f4f6'
                        },
                        ticks: {
                            color: '#6b7280',
                            font: { size: 12 }
                        }
                    }
                }
            }
        });

        scheduleChartResize();
        bindResize();

        loadStockData();
        setupEventListeners();
    }

    var resizeTimeout;

    function scheduleChartResize() {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                if (jslStockChart) {
                    jslStockChart.resize();
                }
            });
        });
    }

    function bindResize() {
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function () {
                if (jslStockChart) {
                    jslStockChart.resize();
                }
            }, 100);
        });
    }

    function setupEventListeners() {
        document.querySelectorAll('input[name="stock_exchange"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                currentExchange = this.value;
                loadStockData();
            });
        });

        document.querySelectorAll('.stock-period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (this.dataset.period === 'Custom') {
                    return;
                }
                document.querySelectorAll('.stock-period-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                currentPeriod = this.dataset.period;
                loadStockData();
            });
        });
    }

    function loadStockData() {
        var loading = document.getElementById('jsl-stock-chart-loading');
        var canvas = document.getElementById('jsl-stock-chart-canvas');

        if (loading) loading.style.display = 'flex';
        if (canvas) canvas.style.opacity = '0.5';

        var formData = new FormData();
        formData.append('action', 'get_stock_data');
        formData.append('symbol', symbol);
        formData.append('exchange', currentExchange);
        formData.append('period', currentPeriod);
        formData.append('nonce', stockChartAjax.nonce);

        fetch(stockChartAjax.ajax_url, {
            method: 'POST',
            body: formData
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (result.success && result.data && result.data.data) {
                    updateChartData(result.data);
                    updatePriceInfo(result.data);
                } else {
                    console.error('Error loading stock data:', result.data);
                    clearChartData();
                    clearPriceInfo();
                }
                if (loading) loading.style.display = 'none';
                if (canvas) canvas.style.opacity = '1';
            })
            .catch(function (error) {
                console.error('Error loading stock data:', error);
                clearChartData();
                clearPriceInfo();
                if (loading) loading.style.display = 'none';
                if (canvas) canvas.style.opacity = '1';
            });
    }

    function updateChartData(data) {
        if (!jslStockChart || !data.data) return;

        var labels = data.data.map(function (item) {
            var date = new Date(item.date);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
            });
        });

        var prices = data.data.map(function (item) { return item.close; });

        jslStockChart.data.labels = labels;
        jslStockChart.data.datasets[0].data = prices;
        jslStockChart.update('none');
        scheduleChartResize();
    }

    function updatePriceInfo(data) {
        var priceElement = document.getElementById('jsl-stock-price');
        var changeElement = document.getElementById('jsl-stock-change');
        var changePercentElement = document.getElementById('jsl-stock-change-percent');
        var dateElement = document.getElementById('jsl-stock-date');

        if (priceElement && data.current_price) {
            priceElement.textContent = data.current_price.toFixed(2);
        }

        if (changeElement && data.change !== undefined) {
            var change = data.change;
            var changeValue = data.current_price - (data.current_price / (1 + change / 100));
            changeElement.textContent = changeValue >= 0 ? '+' + changeValue.toFixed(2) : changeValue.toFixed(2);
            changeElement.className = 'stock-price-change' + (change >= 0 ? '' : ' negative');

            if (changePercentElement) {
                changePercentElement.textContent = '(' + (change >= 0 ? '+' : '') + change.toFixed(2) + '%)';
                changePercentElement.className = 'stock-price-change-percent' + (change >= 0 ? '' : ' negative');
            }
        }

        if (dateElement && data.data && data.data.length > 0) {
            var lastDate = new Date(data.data[data.data.length - 1].date);
            dateElement.textContent = lastDate.toLocaleDateString('en-US', {
                month: '2-digit',
                day: '2-digit',
                year: 'numeric'
            });
        }
    }

    function clearChartData() {
        if (jslStockChart) {
            jslStockChart.data.labels = [];
            jslStockChart.data.datasets[0].data = [];
            jslStockChart.update('none');
        }
    }

    function clearPriceInfo() {
        var priceElement = document.getElementById('jsl-stock-price');
        var changeElement = document.getElementById('jsl-stock-change');
        var changePercentElement = document.getElementById('jsl-stock-change-percent');
        var dateElement = document.getElementById('jsl-stock-date');

        if (priceElement) priceElement.textContent = '--';
        if (changeElement) {
            changeElement.textContent = '--';
            changeElement.className = 'stock-price-change';
        }
        if (changePercentElement) {
            changePercentElement.textContent = '(--)';
            changePercentElement.className = 'stock-price-change-percent';
        }
        if (dateElement) dateElement.textContent = '--';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJSLStockChart);
    } else {
        initJSLStockChart();
    }
})();
