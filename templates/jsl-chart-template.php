<?php
$symbol        = isset($atts['symbol'])       ? $atts['symbol']       : 'JSL';
$company_name  = isset($atts['company_name']) ? $atts['company_name'] : 'Jindal Stainless Ltd. (JSL)';
$default_exchange = isset($atts['exchange'])  ? $atts['exchange']     : 'NSE';
?>
<div id="jsl-stock-chart-container" class="stock-chart-wrapper"
     data-symbol="<?php echo esc_attr($symbol); ?>"
     data-exchange="<?php echo esc_attr($default_exchange); ?>">

    <div class="stock-exchange-switcher">
        <label class="exchange-radio">
            <input type="radio" name="jsl_stock_exchange" value="NSE" <?php echo $default_exchange === 'NSE' ? 'checked' : ''; ?>>
            <span>NSE</span>
        </label>
        <label class="exchange-radio">
            <input type="radio" name="jsl_stock_exchange" value="BSE" <?php echo $default_exchange === 'BSE' ? 'checked' : ''; ?>>
            <span>BSE</span>
        </label>
    </div>

    <div class="stock-chart-header">
        <div class="stock-title-section">
            <h3 class="stock-company-name"><?php echo esc_html($company_name); ?></h3>
            <div class="stock-price-display">
                <span class="stock-current-price" id="jsl-stock-price">--</span>
                <span class="stock-price-change-wrapper">
                    <span class="stock-price-change" id="jsl-stock-change">--</span>
                    <span class="stock-price-change-percent" id="jsl-stock-change-percent">(--)</span>
                </span>
            </div>
        </div>
        <div class="stock-date-section">
            <?php
                $ist = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                $h   = (int) $ist->format('G');
                $m   = (int) $ist->format('i');
                $day = (int) $ist->format('N'); // 1=Mon … 7=Sun
                $mins = $h * 60 + $m;
                $isOpen = ($day <= 5) && ($mins >= 9*60+15) && ($mins <= 15*60+30);
            ?>
            <span id="jsl-stock-date"><?php echo $ist->format('l, j M Y'); ?></span>
            <span id="jsl-stock-status" class="market-status <?php echo $isOpen ? 'market-open' : 'market-closed'; ?>">
                <?php echo $isOpen ? 'Open' : 'Closed'; ?>
            </span>
        </div>
    </div>

    <div class="stock-chart-canvas-wrapper">
        <canvas id="jsl-stock-chart-canvas"></canvas>
        <div id="jsl-stock-chart-loading" class="stock-chart-loading" style="display:none;">
            <div class="loading-spinner"></div>
            <span>Loading chart data...</span>
        </div>
    </div>

    <!-- Custom date range picker (hidden until Custom clicked) -->
    <div class="jsl-custom-date-range" id="jsl-custom-date-range" style="display:none;">
        <label>From: <input type="date" id="jsl-date-from"></label>
        <label>To: <input type="date" id="jsl-date-to"></label>
        <button id="jsl-custom-date-apply">Apply</button>
    </div>

    <!-- Error notification (hidden by default) -->
    <div id="jsl-chart-error" style="display:none;"></div>

    <div class="stock-period-selector">
        <button class="stock-period-btn" data-period="1D">1D</button>
        <button class="stock-period-btn" data-period="1M">1M</button>
        <button class="stock-period-btn" data-period="3M">3M</button>
        <button class="stock-period-btn" data-period="6M">6M</button>
        <button class="stock-period-btn active" data-period="1Y">1Y</button>
        <button class="stock-period-btn" data-period="2Y">2Y</button>
        <button class="stock-period-btn" data-period="Custom" id="jsl-custom-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </button>
    </div>
</div>

<?php if (!empty($inline_stock_data)): ?>
<script>window.jslInlineStockData = <?php echo wp_json_encode($inline_stock_data); ?>;</script>
<?php endif; ?>

<!-- stock-chart.js auto-discovers this container via data-symbol / data-exchange -->
