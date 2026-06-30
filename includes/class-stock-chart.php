<?php
class StockChart {
    private $apiKey;

    public function __construct() {
        $this->apiKey = get_option('stock_chart_api_key', 'demo');
        
        add_shortcode('stock_chart', array($this, 'shortcode'));
        
        add_action('wp_ajax_get_stock_data', array($this, 'ajax_get_stock_data'));
        add_action('wp_ajax_nopriv_get_stock_data', array($this, 'ajax_get_stock_data'));
    }
    
    public function enqueue_scripts() {
        // Preconnect to Yahoo Finance — cuts DNS + TLS cost on first data fetch
        add_action('wp_head', function () {
            static $added = false;
            if ($added) return;
            $added = true;
            echo '<link rel="preconnect" href="https://query1.finance.yahoo.com">' . "\n";
            echo '<link rel="dns-prefetch" href="https://query1.finance.yahoo.com">' . "\n";
        }, 2);

        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_script('stock-chart-js', STOCK_CHART_PLUGIN_URL . 'assets/js/stock-chart.js', array('jquery', 'chart-js'), filemtime(STOCK_CHART_PLUGIN_PATH . 'assets/js/stock-chart.js'), true);
        wp_enqueue_style('stock-chart-css', STOCK_CHART_PLUGIN_URL . 'assets/css/stock-chart.css', array(), '1.0.0');

        wp_localize_script('stock-chart-js', 'stockChartAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('stock_chart_nonce')
        ));
    }

    public function shortcode($atts) {
        $this->enqueue_scripts();

        $default_template = get_option('stock_chart_default_template', 'jsl');

        $atts = shortcode_atts(array(
            'symbol'       => 'JSL',
            'exchange'     => 'NSE',
            'company_name' => 'Jindal Stainless Ltd. (JSL)',
            'template'     => $default_template
        ), $atts);

        // Pre-fetch default (1Y) data server-side so JS skips its first AJAX call
        $inline_stock_data = $this->getStockData($atts['symbol'], $atts['exchange'], '1Y');

        ob_start();

        if ($atts['template'] === 'jsl') {
            include STOCK_CHART_PLUGIN_PATH . 'templates/jsl-chart-template.php';
        } else {
            $uniqueId = 'stock-chart-' . uniqid();
            include STOCK_CHART_PLUGIN_PATH . 'templates/chart-template.php';
        }

        return ob_get_clean();
    }
    
    public function ajax_get_stock_data() {
        check_ajax_referer('stock_chart_nonce', 'nonce');

        $symbol    = sanitize_text_field($_POST['symbol']);
        $exchange  = sanitize_text_field($_POST['exchange']);
        $period    = sanitize_text_field($_POST['period']);
        $startDate = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $endDate   = isset($_POST['end_date'])   ? sanitize_text_field($_POST['end_date'])   : '';

        if ($period === 'Custom') {
            if (!$startDate || !$endDate) {
                wp_send_json_error(array('message' => 'Please select a valid date range.'));
                return;
            }
            $data = $this->getYahooFinanceData($symbol, $exchange, 'Custom', $startDate, $endDate);
        } else {
            $data = $this->getStockData($symbol, $exchange, $period);
        }

        if ($data && !empty($data['data'])) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => 'Unable to fetch stock data.'));
        }
    }
    
    private function getCacheTimeout($period) {
        $ist  = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $day  = (int) $ist->format('N');       // 1=Mon … 7=Sun
        $mins = (int) $ist->format('G') * 60 + (int) $ist->format('i');
        $marketOpen = $day <= 5 && $mins >= (9 * 60 + 15) && $mins <= (15 * 60 + 30);

        switch ($period) {
            case '1D':          return $marketOpen ? 180  : 3600;  // 3 min live / 1 hr closed
            case '1M': case '3M': case '6M':
                                return $marketOpen ? 300  : 1800;  // 5 min / 30 min
            case '1Y': case '2Y':
                                return 3600;                       // historical: 1 hr always
            default:            return $marketOpen ? 300  : 1800;
        }
    }

    private function getStockData($symbol, $exchange, $period) {
        $cacheKey = "stock_data_{$symbol}_{$exchange}_{$period}_" . date('Y-m-d-H');

        $cached = get_transient($cacheKey);
        if ($cached !== false && !empty($cached) && !empty($cached['data'])) {
            return $cached;
        }

        $data = $this->fetchRealStockData($symbol, $exchange, $period);

        if ($data && !empty($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
            set_transient($cacheKey, $data, $this->getCacheTimeout($period));
            return $data;
        }

        return false;
    }
    
    private function fetchRealStockData($symbol, $exchange, $period) {
        $yahooData = $this->getYahooFinanceData($symbol, $exchange, $period);
        if ($yahooData) {
            return $yahooData;
        }
        
        if ($this->apiKey && $this->apiKey !== 'demo' && $this->apiKey !== '') {
            $alphaData = $this->getAlphaVantageData($symbol, $exchange, $period);
            if ($alphaData) {
                return $alphaData;
            }
        }
        
        return false;
    }
    
    private function getYahooFinanceData($symbol, $exchange, $period, $startDate = '', $endDate = '') {
        $yahooSymbol = $this->formatSymbolForYahoo($symbol, $exchange);

        if ($period === 'Custom' && $startDate && $endDate) {
            $ist  = new DateTimeZone('Asia/Kolkata');
            $fromDt = new DateTime($startDate . ' 00:00:00', $ist);
            $toDt   = new DateTime($endDate   . ' 23:59:59', $ist);
            $from   = $fromDt->getTimestamp();
            $to     = $toDt->getTimestamp();
            $url  = "https://query1.finance.yahoo.com/v8/finance/chart/{$yahooSymbol}?period1={$from}&period2={$to}&interval=1d";
        } else {
            $timeRange = $this->getYahooTimeRange($period);
            $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$yahooSymbol}?range={$timeRange['range']}&interval={$timeRange['interval']}";
        }

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            )
        ));

        if (is_wp_error($response)) return false;

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['chart']['result'][0]['indicators']['quote'][0])) return false;

        return $this->processYahooData($data, $symbol, $exchange, $period);
    }

    private function formatSymbolForYahoo($symbol, $exchange) {
        if ($exchange === 'NSE') {
            return $symbol . '.NS';
        } elseif ($exchange === 'BSE') {
            return $symbol . '.BO';
        }
        return $symbol;
    }
    
    private function getYahooTimeRange($period) {
        switch ($period) {
            case '1D':  return array('range' => '1d',  'interval' => '5m');
            case '1W':  return array('range' => '5d',  'interval' => '1h');
            case '1M':  return array('range' => '1mo', 'interval' => '1d');
            case '3M':  return array('range' => '3mo', 'interval' => '1d');
            case '6M':  return array('range' => '6mo', 'interval' => '1d');
            case 'YTD': return array('range' => 'ytd', 'interval' => '1d');
            case '1Y':  return array('range' => '1y',  'interval' => '1d');
            case '2Y':  return array('range' => '2y',  'interval' => '1wk');
            case '5Y':  return array('range' => '5y',  'interval' => '1wk');
            case '10Y': return array('range' => '10y', 'interval' => '1mo');
            case 'Max': return array('range' => 'max', 'interval' => '1mo');
            default:    return array('range' => '1mo', 'interval' => '1d');
        }
    }

    private function processYahooData($data, $symbol, $exchange, $period) {
        $result     = $data['chart']['result'][0];
        $timestamps = $result['timestamp'];
        $quotes     = $result['indicators']['quote'][0];

        $processedData = array();

        $ist = new DateTimeZone('Asia/Kolkata');
        for ($i = 0; $i < count($timestamps); $i++) {
            if (isset($quotes['close'][$i]) && $quotes['close'][$i] !== null) {
                $dt = new DateTime('@' . $timestamps[$i]);
                $dt->setTimezone($ist);
                $processedData[] = array(
                    'date'   => $dt->format('Y-m-d\TH:i:sP'), // ISO-8601 with IST offset
                    'open'   => isset($quotes['open'][$i])   ? $quotes['open'][$i]   : $quotes['close'][$i],
                    'high'   => isset($quotes['high'][$i])   ? $quotes['high'][$i]   : $quotes['close'][$i],
                    'low'    => isset($quotes['low'][$i])    ? $quotes['low'][$i]    : $quotes['close'][$i],
                    'close'  => $quotes['close'][$i],
                    'volume' => isset($quotes['volume'][$i]) ? $quotes['volume'][$i] : 0
                );
            }
        }

        if (empty($processedData)) return false;

        $change = $this->calculateChange($processedData);

        return array(
            'symbol'        => $symbol,
            'exchange'      => $exchange,
            'period'        => $period,
            'data'          => $processedData,
            'current_price' => end($processedData)['close'],
            'change'        => $change['percent'],
            'change_abs'    => $change['absolute'],
            'source'        => 'Yahoo Finance'
        );
    }
    
    private function getAlphaVantageData($symbol, $exchange, $period) {
        $function     = $this->getAlphaVantageFunction($period);
        $alphaSymbols = array();
        
        if ($exchange === 'NSE') {
            $alphaSymbols[] = $symbol . '.NS';
            $alphaSymbols[] = 'JINDALSTL.NS';
            $alphaSymbols[] = 'JINDALSTAINLESS.NS';
        } elseif ($exchange === 'BSE') {
            $alphaSymbols[] = $symbol . '.BO';
            $alphaSymbols[] = 'JINDALSTL.BO';
        } else {
            $alphaSymbols[] = $symbol;
        }
        
        foreach ($alphaSymbols as $alphaSymbol) {
            $url = "https://www.alphavantage.co/query?function={$function}&symbol={$alphaSymbol}&apikey={$this->apiKey}&datatype=json";
            
            $response = wp_remote_get($url, array('timeout' => 15));
            
            if (is_wp_error($response)) continue;
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['Error Message']))  continue;
            if (isset($data['Note']))           continue;
            if (isset($data['Information']))    continue;
            
            $processed = $this->processAlphaVantageData($data, $symbol, $exchange, $period);
            if ($processed) {
                return $processed;
            }
        }
        
        return false;
    }
    
    private function getPeriodDays($period) {
        switch ($period) {
            case '1D':  return 1;
            case '1W':  return 7;
            case '1M':  return 30;
            case '1Y':  return 365;
            case 'Max': return 1825;
            default:    return 30;
        }
    }
    
    private function calculateChange($data) {
        if (count($data) < 2) return array('percent' => 0, 'absolute' => 0);

        $latest   = end($data)['close'];
        $first    = reset($data)['close'];
        $absolute = $latest - $first;
        $percent  = $first > 0 ? ($absolute / $first) * 100 : 0;

        return array(
            'percent'  => round($percent, 2),
            'absolute' => round($absolute, 2)
        );
    }
    
    private function getAlphaVantageFunction($period) {
        switch ($period) {
            case '1D':
            case '1W':
            case '1M':
                return 'TIME_SERIES_DAILY';
            case '1Y':
            case 'Max':
                return 'TIME_SERIES_WEEKLY';
            default:
                return 'TIME_SERIES_DAILY';
        }
    }
    
    private function processAlphaVantageData($data, $symbol, $exchange, $period) {
        $timeSeriesKey = '';
        if (isset($data['Time Series (Daily)'])) {
            $timeSeriesKey = 'Time Series (Daily)';
        } elseif (isset($data['Weekly Time Series'])) {
            $timeSeriesKey = 'Weekly Time Series';
        }
        
        if (!$timeSeriesKey || !isset($data[$timeSeriesKey])) {
            return false;
        }
        
        $timeSeries    = $data[$timeSeriesKey];
        $processedData = array();
        
        foreach ($timeSeries as $date => $values) {
            $processedData[] = array(
                'date'   => $date,
                'open'   => floatval($values['1. open']),
                'high'   => floatval($values['2. high']),
                'low'    => floatval($values['3. low']),
                'close'  => floatval($values['4. close']),
                'volume' => intval($values['5. volume'])
            );
        }
        
        usort($processedData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        $days          = $this->getPeriodDays($period);
        $processedData = array_slice($processedData, -$days);
        
        $change = $this->calculateChange($processedData);

        return array(
            'symbol'        => $symbol,
            'exchange'      => $exchange,
            'period'        => $period,
            'data'          => $processedData,
            'current_price' => end($processedData)['close'],
            'change'        => $change['percent'],
            'change_abs'    => $change['absolute'],
            'source'        => 'Alpha Vantage'
        );
    }
}
