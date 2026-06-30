<?php
class StockChartAdmin {
    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function admin_menu() {
        add_options_page(
            'Stock Chart Settings',
            'Stock Chart',
            'manage_options',
            'stock-chart',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        register_setting('stock_chart_settings', 'stock_chart_api_key');
        register_setting('stock_chart_settings', 'stock_chart_cache_timeout');
        register_setting('stock_chart_settings', 'stock_chart_default_template');
        
        add_settings_section(
            'stock_chart_api_section',
            'API Settings',
            array($this, 'api_section_callback'),
            'stock-chart'
        );
        
        add_settings_field(
            'stock_chart_api_key',
            'Alpha Vantage API Key',
            array($this, 'api_key_callback'),
            'stock-chart',
            'stock_chart_api_section'
        );
        
        add_settings_field(
            'stock_chart_cache_timeout',
            'Cache Timeout (seconds)',
            array($this, 'cache_timeout_callback'),
            'stock-chart',
            'stock_chart_api_section'
        );
        
        add_settings_section(
            'stock_chart_template_section',
            'Template Settings',
            array($this, 'template_section_callback'),
            'stock-chart'
        );
        
        add_settings_field(
            'stock_chart_default_template',
            'Default Template',
            array($this, 'template_callback'),
            'stock-chart',
            'stock_chart_template_section'
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Stock Chart Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('stock_chart_settings');
                do_settings_sections('stock-chart');
                submit_button();
                ?>
            </form>
            
            <h2>API Providers Information</h2>
            
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0;">
                <h3>1. Yahoo Finance (Primary - Free)</h3>
                <p><strong>Status:</strong> ✅ Active (No configuration needed)</p>
                <p><strong>Features:</strong></p>
                <ul>
                    <li>Free to use, no API key required</li>
                    <li>Supports NSE and BSE stocks</li>
                    <li>Real-time and historical data</li>
                    <li>No rate limits (reasonable use)</li>
                </ul>
                <p><strong>Limitations:</strong> May have occasional downtime or rate limiting</p>
            </div>
            
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0;">
                <h3>2. Alpha Vantage (Fallback - Paid Premium Available)</h3>
                <p><strong>Status:</strong> <?php echo (!empty(get_option('stock_chart_api_key', '')) && get_option('stock_chart_api_key', '') !== 'demo') ? '✅ Active' : '⚠️ Inactive (No API key)'; ?></p>
                
                <h4>Free Tier:</h4>
                <ul>
                    <li><strong>Get Free API Key:</strong> <a href="https://www.alphavantage.co/support/#api-key" target="_blank">https://www.alphavantage.co/support/#api-key</a></li>
                    <li>5 API calls per minute</li>
                    <li>500 API calls per day</li>
                    <li>Good for testing and low-traffic sites</li>
                </ul>
                
                <h4>Premium Plans:</h4>
                <ul>
                    <li><strong>Website:</strong> <a href="https://www.alphavantage.co/premium/" target="_blank">https://www.alphavantage.co/premium/</a></li>
                    <li><strong>Starter:</strong> $49.99/month - 75 API calls/minute, 1,200 calls/day</li>
                    <li><strong>Growth:</strong> $149.99/month - 120 API calls/minute, 5,000 calls/day</li>
                    <li><strong>Pro:</strong> $249.99/month - 300 API calls/minute, 15,000 calls/day</li>
                    <li><strong>Enterprise:</strong> Custom pricing - Higher limits, dedicated support</li>
                </ul>
                <p><strong>Benefits of Premium:</strong> Higher rate limits, better data quality, priority support, more reliable uptime</p>
            </div>
            
            <h2>Usage</h2>
            <p>Use the shortcode: <code>[stock_chart symbol="JSL" exchange="NSE"]</code></p>
            
            <h3>Available Parameters:</h3>
            <ul>
                <li><strong>symbol</strong>: Stock symbol (e.g., JSL, RELIANCE, TCS)</li>
                <li><strong>exchange</strong>: NSE or BSE</li>
                <li><strong>company_name</strong>: Company name to display (default: "Jindal Stainless Ltd. (JSL)")</li>
                <li><strong>template</strong>: Template to use - "jsl" or "default" (default: uses setting above)</li>
            </ul>
            
            <h3>Template Examples:</h3>
            <ul>
                <li><code>[stock_chart symbol="JSL" exchange="NSE"]</code> - Uses default template from settings</li>
                <li><code>[stock_chart symbol="JSL" exchange="NSE" template="jsl"]</code> - Force JSL template</li>
                <li><code>[stock_chart symbol="JSL" exchange="NSE" template="default"]</code> - Force default template</li>
            </ul>
            
            <h2>API Fallback Process</h2>
            <ol>
                <li><strong>Yahoo Finance</strong> - Tried first (free, no API key needed)</li>
                <li><strong>Alpha Vantage</strong> - Used if Yahoo Finance fails AND API key is set</li>
                <li><strong>Sample Data</strong> - Generated if both APIs fail (for demonstration)</li>
            </ol>
        </div>
        <?php
    }
    
    public function api_section_callback() {
        echo '<p>Configure your API settings for fetching stock data. The plugin uses Yahoo Finance (free) by default, with Alpha Vantage (paid) as a fallback option.</p>';
    }
    
    public function api_key_callback() {
        $value = get_option('stock_chart_api_key', '');
        $isSet = !empty($value) && $value !== 'demo';
        
        echo '<input type="text" name="stock_chart_api_key" value="' . esc_attr($value) . '" size="50" class="regular-text" placeholder="Enter your Alpha Vantage API key" />';
        
        if ($isSet) {
            echo '<span style="color: green; margin-left: 10px;">✓ API Key is set</span>';
        } else {
            echo '<span style="color: #d63638; margin-left: 10px;">⚠ No API key set (using free Yahoo Finance only)</span>';
        }
        
        echo '<div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">';
        echo '<strong>Alpha Vantage API Key:</strong><br>';
        echo '<ul style="margin: 10px 0 0 20px;">';
        echo '<li><strong>Free Tier:</strong> <a href="https://www.alphavantage.co/support/#api-key" target="_blank">Get Free API Key</a> (5 API calls/minute, 500 calls/day)</li>';
        echo '<li><strong>Premium Plans:</strong> <a href="https://www.alphavantage.co/premium/" target="_blank">View Premium Plans</a> (Higher rate limits, better data quality)</li>';
        echo '<li><strong>Pricing:</strong> Starting from $49.99/month for 75 API calls/minute</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">';
        echo '<strong>Current API Status:</strong><br>';
        echo '<ul style="margin: 10px 0 0 20px;">';
        echo '<li>✅ <strong>Yahoo Finance:</strong> Active (Free, no API key required)</li>';
        if ($isSet) {
            echo '<li>✅ <strong>Alpha Vantage:</strong> Active (API key configured)</li>';
        } else {
            echo '<li>⚠️ <strong>Alpha Vantage:</strong> Inactive (No API key set)</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    public function cache_timeout_callback() {
        $value = get_option('stock_chart_cache_timeout', 300);
        echo '<input type="number" name="stock_chart_cache_timeout" value="' . esc_attr($value) . '" min="60" max="3600" />';
        echo '<p class="description">Cache timeout in seconds (60-3600)</p>';
    }
    
    public function template_section_callback() {
        echo '<p>Choose the default template style for stock charts. You can override this in individual shortcodes using the <code>template</code> parameter.</p>';
    }
    
    public function template_callback() {
        $value = get_option('stock_chart_default_template', 'jsl');
        $templates = array(
            'jsl' => 'JSL Custom Template (Recommended)',
            'default' => 'Default Template'
        );
        
        echo '<select name="stock_chart_default_template">';
        foreach ($templates as $key => $label) {
            $selected = selected($value, $key, false);
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        
        echo '<p class="description">';
        echo '<strong>JSL Custom Template:</strong> Custom design matching your theme (uses jsl-stock-chart-init.js)<br>';
        echo '<strong>Default Template:</strong> Original plugin template (uses stock-chart.js)';
        echo '</p>';
        
        echo '<div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">';
        echo '<strong>Available Templates:</strong><br>';
        echo '<ul style="margin: 10px 0 0 20px;">';
        echo '<li><strong>jsl</strong> - Custom JSL template with exchange switcher, period selector, and custom styling</li>';
        echo '<li><strong>default</strong> - Original plugin template with basic chart display</li>';
        echo '</ul>';
        echo '<p style="margin-top: 10px;"><strong>Note:</strong> You can override the default template in shortcode: <code>[stock_chart template="default"]</code></p>';
        echo '</div>';
    }
}