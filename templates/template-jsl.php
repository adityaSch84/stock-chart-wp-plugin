<?php
/**
 * JSL investors-style chart (orange border, full period strip, Chart.js via plugin JS).
 *
 * Same usage as Jindal Stainless theme front page:
 *   echo do_shortcode( '[stock_chart symbol="JSL" exchange="NSE" company_name="Jindal Stainless Ltd. (JSL)" template="jsl"]' );
 *
 * Shortcode: template="jsl"
 * Assets (enqueued by plugin): assets/css/jsl-stock-chart.css, assets/js/jsl-stock-chart.js
 *
 * @package stock-chart-plugin
 *
 * @var array $atts Shortcode attributes (symbol, exchange, company_name, exchange_display, …).
 */

$symbol = isset($atts['symbol']) ? $atts['symbol'] : 'JSL';
$company_name = isset($atts['company_name']) ? $atts['company_name'] : 'Jindal Stainless Ltd. (JSL)';
$default_exchange = isset($atts['exchange']) ? $atts['exchange'] : 'NSE';
$exchange_display = isset($atts['exchange_display']) ? strtolower((string) $atts['exchange_display']) : 'both';
if (!in_array($exchange_display, array('nse', 'bse', 'both'), true)) {
    $exchange_display = 'both';
}
$show_nse_tab = ($exchange_display === 'nse' || $exchange_display === 'both');
$show_bse_tab = ($exchange_display === 'bse' || $exchange_display === 'both');
?>
<div id="jsl-stock-chart-container" class="stock-chart-wrapper" data-lenis-prevent-wheel data-lenis-prevent-touch data-stock-symbol="<?php echo esc_attr($symbol); ?>" data-exchange-mode="<?php echo esc_attr($exchange_display); ?>">
    <?php if ($show_nse_tab || $show_bse_tab) : ?>
    <div class="stock-exchange-switcher">
        <?php if ($show_nse_tab) : ?>
        <label class="exchange-radio">
            <input type="radio" name="stock_exchange" value="NSE" <?php echo $default_exchange === 'NSE' ? 'checked' : ''; ?>>
            <span>NSE</span>
        </label>
        <?php endif; ?>
        <?php if ($show_bse_tab) : ?>
        <label class="exchange-radio">
            <input type="radio" name="stock_exchange" value="BSE" <?php echo $default_exchange === 'BSE' ? 'checked' : ''; ?>>
            <span>BSE</span>
        </label>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
            <span id="jsl-stock-date"><?php echo esc_html(wp_date('m/d/Y')); ?></span>
            <span id="jsl-stock-status"><?php esc_html_e('Closed', 'stock-chart'); ?></span>
        </div>
    </div>

    <div class="stock-chart-canvas-wrapper">
        <canvas id="jsl-stock-chart-canvas"></canvas>
        <div id="jsl-stock-chart-loading" class="stock-chart-loading" style="display: none;">
            <div class="loading-spinner"></div>
            <span><?php esc_html_e('Loading chart data…', 'stock-chart'); ?></span>
        </div>
    </div>

    <div class="stock-period-selector">
        <button type="button" class="stock-period-btn" data-period="1D">1d</button>
        <button type="button" class="stock-period-btn" data-period="1W">5d</button>
        <button type="button" class="stock-period-btn" data-period="1M">1m</button>
        <button type="button" class="stock-period-btn" data-period="3M">3m</button>
        <button type="button" class="stock-period-btn" data-period="6M">6m</button>
        <button type="button" class="stock-period-btn" data-period="YTD">YTD</button>
        <button type="button" class="stock-period-btn active" data-period="1Y">1y</button>
        <button type="button" class="stock-period-btn" data-period="2Y">2y</button>
        <button type="button" class="stock-period-btn" data-period="5Y">5y</button>
        <button type="button" class="stock-period-btn" data-period="10Y">10y</button>
        <button type="button" class="stock-period-btn" data-period="Max">Max</button>
        <button type="button" class="stock-period-btn" data-period="Custom" aria-label="<?php esc_attr_e('Custom range', 'stock-chart'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </button>
    </div>
</div>
