let stockCharts = {};
let currentData = {};

function initStockChart(containerId, symbol, exchange) {
    const ctx = document.getElementById("chart-" + containerId).getContext("2d");
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(251, 146, 60, 0.1)");
    gradient.addColorStop(1, "rgba(251, 146, 60, 0.01)");
    
    stockCharts[containerId] = new Chart(ctx, {
        type: "line",
        data: {
            labels: [],
            datasets: [{
                label: "Stock Price",
                data: [],
                borderColor: "#fb923c",
                backgroundColor: gradient,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: "#fb923c",
                pointHoverBorderColor: "#fff",
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: "index"
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgba(0, 0, 0, 0.8)",
                    titleColor: "#fff",
                    bodyColor: "#fff",
                    borderColor: "#fb923c",
                    borderWidth: 1,
                    cornerRadius: 6,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return "₹ " + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: "#6b7280",
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    display: false,
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Load initial data
    loadStockData(containerId, symbol, exchange, "1M");
}

function loadStockData(containerId, symbol, exchange, period) {
    const loading = document.getElementById("loading-" + containerId);
    const chart = document.getElementById("chart-" + containerId);
    
    loading.style.display = "flex";
    chart.style.opacity = "0.5";
    
    const formData = new FormData();
    formData.append("action", "get_stock_data");
    formData.append("symbol", symbol);
    formData.append("exchange", exchange);
    formData.append("period", period);
    formData.append("nonce", stockChartAjax.nonce);
    
    fetch(stockChartAjax.ajax_url, {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            updateChartData(containerId, result.data);
            updatePriceInfo(containerId, result.data);
            currentData[containerId] = result.data;
        } else {
            console.error("Error loading stock data:", result.data);
        }
        loading.style.display = "none";
        chart.style.opacity = "1";
    })
    .catch(error => {
        console.error("Error loading stock data:", error);
        loading.style.display = "none";
        chart.style.opacity = "1";
    });
}

function updateChartData(containerId, data) {
    const chart = stockCharts[containerId];
    if (!chart || !data.data) return;
    
    const labels = data.data.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString("en-US", { 
            month: "short", 
            day: "numeric" 
        });
    });
    
    const prices = data.data.map(item => item.close);
    
    chart.data.labels = labels;
    chart.data.datasets[0].data = prices;
    chart.update('none');
}

function updatePriceInfo(containerId, data) {
    const priceElement = document.getElementById("current-price-" + containerId);
    const changeElement = document.getElementById("price-change-" + containerId);
    
    if (priceElement) {
        priceElement.textContent = data.current_price.toFixed(2);
    }
    
    if (changeElement) {
        const change = data.change;
        changeElement.textContent = (change >= 0 ? "+" : "") + change.toFixed(2) + "%";
        changeElement.parentElement.className = "price-change " + (change >= 0 ? "positive" : "negative");
    }
}

function switchExchange(containerId, exchange) {
    const container = document.getElementById(containerId);
    const tabs = container.querySelectorAll(".exchange-tab");
    const activeFilter = container.querySelector(".time-filter.active");
    
    tabs.forEach(tab => {
        tab.classList.toggle("active", tab.dataset.exchange === exchange);
    });
    
    const symbol = currentData[containerId] ? currentData[containerId].symbol : "RELIANCE";
    const period = activeFilter ? activeFilter.dataset.period : "1M";
    
    loadStockData(containerId, symbol, exchange, period);
}

function updateChart(containerId, period) {
    const container = document.getElementById(containerId);
    const filters = container.querySelectorAll(".time-filter");
    const activeTab = container.querySelector(".exchange-tab.active");
    
    filters.forEach(filter => {
        filter.classList.toggle("active", filter.dataset.period === period);
    });
    
    const symbol = currentData[containerId] ? currentData[containerId].symbol : "RELIANCE";
    const exchange = activeTab ? activeTab.dataset.exchange : "NSE";
    
    loadStockData(containerId, symbol, exchange, period);
}