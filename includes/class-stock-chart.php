<?php
class StockChart {
    private $apiKey;
    private $cacheTimeout;
    
    public function __construct() {
        $this->apiKey = get_option('stock_chart_api_key', 'demo');
        $this->cacheTimeout = get_option('stock_chart_cache_timeout', 300);
        
        add_shortcode('stock_chart', array($this, 'shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_get_stock_data', array($this, 'ajax_get_stock_data'));
        add_action('wp_ajax_nopriv_get_stock_data', array($this, 'ajax_get_stock_data'));
    }
    
    /**
     * Load assets when shortcode renders (JSL template uses bundled jsl-stock-chart.*).
     */
    private function enqueue_chart_assets($template, $symbol, $default_exchange) {
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);

        if ($template === 'jsl') {
            wp_enqueue_style(
                'stock-chart-jsl',
                STOCK_CHART_PLUGIN_URL . 'assets/css/jsl-stock-chart.css',
                array(),
                '1.2.0'
            );
            wp_enqueue_script(
                'stock-chart-jsl',
                STOCK_CHART_PLUGIN_URL . 'assets/js/jsl-stock-chart.js',
                array('jquery', 'chart-js'),
                '1.2.0',
                true
            );
            wp_localize_script('stock-chart-jsl', 'stockChartAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('stock_chart_nonce'),
                'symbol' => $symbol,
                'default_exchange' => $default_exchange,
            ));
            return;
        }

        wp_enqueue_style('stock-chart-css', STOCK_CHART_PLUGIN_URL . 'assets/css/stock-chart.css', array(), '1.0.0');
        wp_enqueue_script('stock-chart-js', STOCK_CHART_PLUGIN_URL . 'assets/js/stock-chart.js', array('jquery', 'chart-js'), '1.0.0', true);
        wp_localize_script('stock-chart-js', 'stockChartAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stock_chart_nonce'),
        ));
    }

    public function shortcode($atts) {
        $default_template = get_option('stock_chart_default_template', 'jsl');
        $default_symbol = get_option('stock_chart_default_symbol', 'JSL');
        $default_company = get_option('stock_chart_default_company_name', 'Jindal Stainless Ltd. (JSL)');
        $exchange_display = strtolower((string) get_option('stock_chart_exchange_display', 'both'));
        if (!in_array($exchange_display, array('nse', 'bse', 'both'), true)) {
            $exchange_display = 'both';
        }
        $default_exchange = ($exchange_display === 'bse') ? 'BSE' : 'NSE';

        $atts = shortcode_atts(array(
            'symbol' => $default_symbol,
            'exchange' => $default_exchange,
            'company_name' => $default_company,
            'template' => $default_template,
            'width' => '100%',
            'height' => '400px',
        ), $atts, 'stock_chart');

        $atts['template'] = $this->normalize_chart_template_slug($atts['template']);

        $atts['exchange_display'] = $exchange_display;

        if ($exchange_display === 'nse') {
            $atts['exchange'] = 'NSE';
        } elseif ($exchange_display === 'bse') {
            $atts['exchange'] = 'BSE';
        } else {
            $ex = strtoupper($atts['exchange']);
            $atts['exchange'] = ($ex === 'BSE') ? 'BSE' : 'NSE';
        }

        $this->enqueue_chart_assets($atts['template'], $atts['symbol'], $atts['exchange']);

        ob_start();

        if ($atts['template'] === 'jsl') {
            include STOCK_CHART_PLUGIN_PATH . 'templates/template-jsl.php';
        } else {
            $uniqueId = 'stock-chart-' . uniqid();
            include STOCK_CHART_PLUGIN_PATH . 'templates/template-default.php';
        }

        return ob_get_clean();
    }

    /**
     * @param string $template Raw shortcode / option value.
     * @return string "jsl" or "default"
     */
    private function normalize_chart_template_slug($template) {
        return (strtolower((string) $template) === 'jsl') ? 'jsl' : 'default';
    }
    
    public function ajax_get_stock_data() {
        check_ajax_referer('stock_chart_nonce', 'nonce');
        
        $symbol = sanitize_text_field($_POST['symbol']);
        $exchange = sanitize_text_field($_POST['exchange']);
        $period = sanitize_text_field($_POST['period']);
        
        $data = $this->getStockData($symbol, $exchange, $period);
        
        if ($data && !empty($data['data'])) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array(
                'message' => 'Unable to fetch stock data. Please try again later or check if the symbol is correct.'
            ));
        }
    }
    
    private function getStockData($symbol, $exchange, $period) {
        // Create unique cache key for each symbol/exchange/period combination
        $cacheKey = "stock_data_{$symbol}_{$exchange}_{$period}_" . date('Y-m-d-H');
        
        // Check cache first
        $cached = get_transient($cacheKey);
        if ($cached !== false && !empty($cached) && !empty($cached['data'])) {
            return $cached;
        }
        
        // Try to get real data from APIs
        $data = $this->fetchRealStockData($symbol, $exchange, $period);
        
        // Only cache if we have valid data
        if ($data && !empty($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
            set_transient($cacheKey, $data, $this->cacheTimeout);
            return $data;
        }
        
        // Return false if no valid data
        return false;
    }
    
    private function fetchRealStockData($symbol, $exchange, $period) {
        // Method 1: Try Yahoo Finance API (free, most reliable)
        // Yahoo Finance has better support for Indian stocks (NSE/BSE)
        $yahooData = $this->getYahooFinanceData($symbol, $exchange, $period);
        if ($yahooData) {
            return $yahooData;
        }
        
        // Method 2: Try Alpha Vantage if API key is set (paid/premium)
        // Note: Alpha Vantage has limited support for Indian stocks (NSE/BSE)
        // It works better for US stocks
        if ($this->apiKey && $this->apiKey !== 'demo' && $this->apiKey !== '') {
            $alphaData = $this->getAlphaVantageData($symbol, $exchange, $period);
            if ($alphaData) {
                return $alphaData;
            }
        }
        
        return false;
    }
    
    private function getYahooFinanceData($symbol, $exchange, $period) {
        // Format symbol for Yahoo Finance
        $yahooSymbol = $this->formatSymbolForYahoo($symbol, $exchange);
        
        // Get time range
        $timeRange = $this->getYahooTimeRange($period);
        
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$yahooSymbol}?range={$timeRange}&interval=1d";
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['chart']['result'][0]['indicators']['quote'][0])) {
            return false;
        }
        
        return $this->processYahooData($data, $symbol, $exchange, $period);
    }
    
    private function formatSymbolForYahoo($symbol, $exchange) {
        // Convert Indian stock symbols to Yahoo format
        if ($exchange === 'NSE') {
            return $symbol . '.NS';
        } elseif ($exchange === 'BSE') {
            return $symbol . '.BO';
        }
        return $symbol;
    }
    
    private function getYahooTimeRange($period) {
        switch ($period) {
            case '1D':
                return '1d';
            case '1W':
                return '5d';
            case '1M':
                return '1mo';
            case '3M':
                return '3mo';
            case '6M':
                return '6mo';
            case 'YTD':
                return 'ytd';
            case '1Y':
                return '1y';
            case '2Y':
                return '2y';
            case '5Y':
                return '5y';
            case '10Y':
                return '10y';
            case 'Max':
                return 'max';
            case 'Custom':
                return '1y';
            default:
                return '1mo';
        }
    }
    
    private function processYahooData($data, $symbol, $exchange, $period) {
        $result = $data['chart']['result'][0];
        $timestamps = $result['timestamp'];
        $quotes = $result['indicators']['quote'][0];
        
        $processedData = array();
        
        for ($i = 0; $i < count($timestamps); $i++) {
            if (isset($quotes['close'][$i]) && $quotes['close'][$i] !== null) {
                $processedData[] = array(
                    'date' => date('Y-m-d', $timestamps[$i]),
                    'open' => $quotes['open'][$i] ?? $quotes['close'][$i],
                    'high' => $quotes['high'][$i] ?? $quotes['close'][$i],
                    'low' => $quotes['low'][$i] ?? $quotes['close'][$i],
                    'close' => $quotes['close'][$i],
                    'volume' => $quotes['volume'][$i] ?? 0
                );
            }
        }
        
        if (empty($processedData)) {
            return false;
        }
        
        $currentPrice = end($processedData)['close'];
        $change = $this->calculateChange($processedData);
        
        return array(
            'symbol' => $symbol,
            'exchange' => $exchange,
            'period' => $period,
            'data' => $processedData,
            'current_price' => $currentPrice,
            'change' => $change,
            'source' => 'Yahoo Finance'
        );
    }
    
    private function getAlphaVantageData($symbol, $exchange, $period) {
        // Alpha Vantage API implementation
        $function = $this->getAlphaVantageFunction($period);
        
        // Format symbol for Alpha Vantage
        // For Indian stocks, Alpha Vantage uses different formats
        // Try multiple symbol formats for better compatibility
        $alphaSymbols = array();
        
        if ($exchange === 'NSE') {
            // Try different NSE symbol formats
            $alphaSymbols[] = $symbol . '.NS';  // JSL.NS
            $alphaSymbols[] = 'JINDALSTL.NS';  // Full company name
            $alphaSymbols[] = 'JINDALSTAINLESS.NS';  // Alternative
        } elseif ($exchange === 'BSE') {
            // Try different BSE symbol formats
            $alphaSymbols[] = $symbol . '.BO';  // JSL.BO
            $alphaSymbols[] = 'JINDALSTL.BO';  // Full company name
        } else {
            $alphaSymbols[] = $symbol;
        }
        
        // Try each symbol format until one works
        foreach ($alphaSymbols as $alphaSymbol) {
            $url = "https://www.alphavantage.co/query?function={$function}&symbol={$alphaSymbol}&apikey={$this->apiKey}&datatype=json";
            
            $response = wp_remote_get($url, array('timeout' => 15));
            
            if (is_wp_error($response)) {
                continue; // Try next symbol format
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Check for API errors
            if (isset($data['Error Message'])) {
                // API error, try next symbol
                continue;
            }
            
            if (isset($data['Note'])) {
                // Rate limit, but symbol might be valid - try next anyway
                continue;
            }
            
            if (isset($data['Information'])) {
                // API info message, try next symbol
                continue;
            }
            
            // Process Alpha Vantage data
            $processed = $this->processAlphaVantageData($data, $symbol, $exchange, $period);
            if ($processed) {
                return $processed;
            }
        }
        
        // All symbol formats failed
        return false;
    }
    
    
    private function getPeriodDays($period) {
        switch ($period) {
            case '1D':
                return 1;
            case '1W':
                return 7;
            case '1M':
                return 30;
            case '3M':
                return 90;
            case '6M':
                return 180;
            case 'YTD':
                return 366;
            case '1Y':
                return 365;
            case '2Y':
                return 730;
            case '5Y':
                return 1825;
            case '10Y':
                return 3650;
            case 'Max':
                return 7300;
            case 'Custom':
                return 365;
            default:
                return 30;
        }
    }
    
    private function calculateChange($data) {
        if (count($data) < 2) return 0;
        
        $latest = end($data)['close'];
        $previous = prev($data)['close'];
        
        return $previous > 0 ? (($latest - $previous) / $previous) * 100 : 0;
    }
    
    private function getAlphaVantageFunction($period) {
        switch ($period) {
            case '1Y':
            case '2Y':
            case '5Y':
            case '10Y':
            case 'Max':
            case 'YTD':
                return 'TIME_SERIES_WEEKLY';
            default:
                return 'TIME_SERIES_DAILY';
        }
    }
    
    private function processAlphaVantageData($data, $symbol, $exchange, $period) {
        // Process Alpha Vantage response
        $timeSeriesKey = '';
        if (isset($data['Time Series (Daily)'])) {
            $timeSeriesKey = 'Time Series (Daily)';
        } elseif (isset($data['Weekly Time Series'])) {
            $timeSeriesKey = 'Weekly Time Series';
        }
        
        if (!$timeSeriesKey || !isset($data[$timeSeriesKey])) {
            return false;
        }
        
        $timeSeries = $data[$timeSeriesKey];
        $processedData = array();
        
        foreach ($timeSeries as $date => $values) {
            $processedData[] = array(
                'date' => $date,
                'open' => floatval($values['1. open']),
                'high' => floatval($values['2. high']),
                'low' => floatval($values['3. low']),
                'close' => floatval($values['4. close']),
                'volume' => intval($values['5. volume'])
            );
        }
        
        // Sort by date
        usort($processedData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        // Limit to requested period
        $days = $this->getPeriodDays($period);
        $processedData = array_slice($processedData, -$days);
        
        return array(
            'symbol' => $symbol,
            'exchange' => $exchange,
            'period' => $period,
            'data' => $processedData,
            'current_price' => end($processedData)['close'],
            'change' => $this->calculateChange($processedData),
            'source' => 'Alpha Vantage'
        );
    }
    
}