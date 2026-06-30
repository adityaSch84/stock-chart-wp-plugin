<div id="<?php echo esc_attr($uniqueId); ?>" class="stock-chart-container" style="width: <?php echo esc_attr($atts['width']); ?>;">
    <div class="stock-header">
        <div class="stock-info">
            <h2><?php echo esc_html($atts['symbol']); ?></h2>
        </div>
        <div class="stock-price">
            <p class="current-price">₹ <span id="current-price-<?php echo esc_attr($uniqueId); ?>">0.00</span></p>
            <p class="price-change positive">
                <span id="price-change-<?php echo esc_attr($uniqueId); ?>">+0.00%</span>
            </p>
        </div>
    </div>
    
    <div class="exchange-tabs">
        <button class="exchange-tab <?php echo $atts['exchange'] === 'NSE' ? 'active' : ''; ?>" 
                data-exchange="NSE" 
                onclick="switchExchange('<?php echo esc_attr($uniqueId); ?>', 'NSE')">NSE</button>
        <button class="exchange-tab <?php echo $atts['exchange'] === 'BSE' ? 'active' : ''; ?>" 
                data-exchange="BSE" 
                onclick="switchExchange('<?php echo esc_attr($uniqueId); ?>', 'BSE')">BSE</button>
    </div>
    
    <div class="time-filters">
        <button class="time-filter" data-period="1D" onclick="updateChart('<?php echo esc_attr($uniqueId); ?>', '1D')">1D</button>
        <button class="time-filter" data-period="1W" onclick="updateChart('<?php echo esc_attr($uniqueId); ?>', '1W')">1W</button>
        <button class="time-filter active" data-period="1M" onclick="updateChart('<?php echo esc_attr($uniqueId); ?>', '1M')">1M</button>
        <button class="time-filter" data-period="1Y" onclick="updateChart('<?php echo esc_attr($uniqueId); ?>', '1Y')">1Y</button>
        <button class="time-filter" data-period="Max" onclick="updateChart('<?php echo esc_attr($uniqueId); ?>', 'Max')">Max</button>
    </div>
    
    <div class="chart-wrapper" style="height: <?php echo esc_attr($atts['height']); ?>;">
        <canvas id="chart-<?php echo esc_attr($uniqueId); ?>"></canvas>
    </div>
    
    <div id="loading-<?php echo esc_attr($uniqueId); ?>" class="loading" style="display: none;">
        <div class="spinner"></div>
        Loading...
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initStockChart('<?php echo esc_js($uniqueId); ?>', '<?php echo esc_js($atts['symbol']); ?>', '<?php echo esc_js($atts['exchange']); ?>');
});
</script>