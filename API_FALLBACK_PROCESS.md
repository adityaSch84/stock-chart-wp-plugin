# Stock Chart Plugin - API Fallback Process

## Overview
The plugin uses a **sequential fallback system** to fetch stock data. It tries multiple APIs in order until one succeeds, or falls back to sample data if all APIs fail.

---

## API Fallback Chain (Priority Order)

### **Step 1: Cache Check** ⚡
- **Location**: `getStockData()` method
- **Process**: Checks WordPress transients for cached data
- **Cache Key Format**: `stock_data_{symbol}_{exchange}_{period}_{date-hour}`
- **Cache Duration**: 300 seconds (5 minutes) - configurable via `stock_chart_cache_timeout` option
- **If cached data exists**: Returns immediately (no API call)
- **If no cache**: Proceeds to Step 2

---

### **Step 2: Yahoo Finance API** 🥇 (Primary - Most Reliable)
- **Method**: `getYahooFinanceData()`
- **Endpoint**: `https://query1.finance.yahoo.com/v8/finance/chart/{symbol}?range={range}&interval=1d`
- **Symbol Format**:
  - NSE: `{SYMBOL}.NS` (e.g., `JSL.NS`)
  - BSE: `{SYMBOL}.BO` (e.g., `JSL.BO`)
- **Time Ranges**:
  - `1D` → `1d`
  - `1W` → `5d`
  - `1M` → `1mo`
  - `1Y` → `1y`
  - `Max` → `5y`
- **Headers**: Includes User-Agent to avoid blocking
- **Timeout**: 15 seconds
- **Success Criteria**: Response contains `chart.result[0].indicators.quote[0]`
- **If successful**: Returns processed data, stops fallback chain
- **If fails**: Proceeds to Step 3

**Status**: ✅ **Currently Working** (Free, no API key required)

---

### **Step 3: Alpha Vantage API** 🥈 (Secondary - Requires API Key)
- **Method**: `getAlphaVantageData()`
- **Endpoint**: `https://www.alphavantage.co/query?function={function}&symbol={symbol}&apikey={key}&datatype=json`
- **Requirements**: 
  - API key must be set in WordPress options (`stock_chart_api_key`)
  - API key must NOT be `'demo'`
- **Functions Used**:
  - `1D`, `1W`, `1M` → `TIME_SERIES_DAILY`
  - `1Y`, `Max` → `TIME_SERIES_WEEKLY`
- **Timeout**: 15 seconds
- **Success Criteria**: Response contains `Time Series (Daily)` or `Weekly Time Series`
- **If API key not set or is 'demo'**: Skips this step, proceeds to Step 4
- **If successful**: Returns processed data, stops fallback chain
- **If fails**: Proceeds to Step 4

**Status**: ⚠️ **Requires API Key** (Check WordPress admin → Stock Chart settings)

---

### **Step 4: Alternative Free APIs** 🥉 (Tertiary)
- **Method**: `getAlternativeAPIData()`
- **Process**: Tries exchange-specific APIs

#### **4a. NSE Data** (If exchange = NSE)
- **Method**: `getNSEData()`
- **Tries 2 sources sequentially**:

  1. **NSE Official API**
     - Endpoint: `https://www.nseindia.com/api/chart-databyindex?index={symbol}`
     - Timeout: 10 seconds
     - **Status**: ⚠️ May require authentication/cookies
   
  2. **GitHub Repository** (Fallback)
     - Endpoint: `https://api.github.com/repos/maanavshah/stock-market-india/contents/data/nse/{symbol}.json`
     - Timeout: 10 seconds
     - **Status**: ✅ **Free, but limited stock coverage**

- **If successful**: Returns processed data, stops fallback chain
- **If fails**: Proceeds to Step 4b

#### **4b. BSE Data** (If exchange = BSE)
- **Method**: `getBSEData()`
- **Status**: ❌ **Not Implemented** (returns `false` immediately)
- **Reason**: Limited free BSE API options available

**If all alternative APIs fail**: Proceeds to Step 5

---

### **Step 5: Sample Data Generation** 🎲 (Final Fallback)
- **Method**: `generateRealisticSampleData()`
- **Purpose**: Generates realistic-looking sample data when all APIs fail
- **Process**:
  1. Calculates number of days based on period
  2. Uses base prices for known stocks (RELIANCE, TCS, INFY, etc.)
  3. Generates price movements with realistic variations (±2% daily)
  4. Creates OHLC data with volume
- **Base Prices Used**:
  ```php
  'RELIANCE' => 2400,
  'TCS' => 3200,
  'INFY' => 1500,
  'HDFCBANK' => 1600,
  'ICICIBANK' => 900,
  'SBIN' => 500,
  'BHARTIARTL' => 800,
  'ITC' => 400,
  'KOTAKBANK' => 1800,
  'LT' => 2000
  ```
- **Default**: Uses ₹1000 as base price for unknown symbols
- **Data Source**: Marked as `'Sample Data'` in response

**Status**: ✅ **Always Available** (fallback when all APIs fail)

---

## Complete Flow Diagram

```
Request Stock Data
       ↓
[1] Check Cache
    ├─ Cache Hit? → Return Cached Data ✅
    └─ Cache Miss? → Continue
       ↓
[2] Yahoo Finance API
    ├─ Success? → Cache & Return ✅
    └─ Fail? → Continue
       ↓
[3] Alpha Vantage API (if key set)
    ├─ API Key Valid? → Try API
    │   ├─ Success? → Cache & Return ✅
    │   └─ Fail? → Continue
    └─ No API Key? → Skip
       ↓
[4] Alternative APIs
    ├─ NSE Exchange?
    │   ├─ Try NSE Official API
    │   │   ├─ Success? → Cache & Return ✅
    │   │   └─ Fail? → Try GitHub API
    │   │       ├─ Success? → Cache & Return ✅
    │   │       └─ Fail? → Continue
    │   └─ BSE Exchange? → Skip (not implemented)
    └─ Continue
       ↓
[5] Generate Sample Data
    └─ Return Sample Data ✅
```

---

## Current Working Status

### ✅ **Currently Working APIs:**
1. **Yahoo Finance** - Free, no API key required, most reliable
2. **GitHub Repository** (NSE only) - Free, but limited coverage
3. **Sample Data** - Always available as fallback

### ⚠️ **Requires Configuration:**
1. **Alpha Vantage** - Needs API key in WordPress admin settings

### ❌ **Not Working/Not Implemented:**
1. **NSE Official API** - May require authentication
2. **BSE APIs** - Not implemented (returns false)

---

## How to Check Which API is Working

### Method 1: Check Browser Console
1. Open browser Developer Tools (F12)
2. Go to Network tab
3. Filter by "admin-ajax.php"
4. Look for `get_stock_data` request
5. Check response - it includes `source` field:
   - `"source": "Yahoo Finance"` ✅
   - `"source": "Alpha Vantage"` ✅
   - `"source": "NSE Data"` ✅
   - `"source": "Sample Data"` ⚠️ (all APIs failed)

### Method 2: Check WordPress Debug Log
Add this to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `/wp-content/debug.log` for API errors.

### Method 3: Add Debug Output
The plugin can be modified to log which API succeeded. Check the `source` field in the AJAX response.

---

## Recommendations

1. **Primary**: Yahoo Finance is working and free - keep using it
2. **Backup**: Set up Alpha Vantage API key for redundancy
3. **Testing**: Test with different symbols to verify API coverage
4. **Monitoring**: Check the `source` field in responses to track which API is being used

---

## API Key Configuration

To enable Alpha Vantage API:
1. Go to WordPress Admin
2. Navigate to Stock Chart settings (if available)
3. Or use this code in `functions.php`:
```php
update_option('stock_chart_api_key', 'YOUR_ALPHA_VANTAGE_API_KEY');
```

---

## Cache Management

- **Cache Duration**: 300 seconds (5 minutes) by default
- **Cache Key**: Includes symbol, exchange, period, and hour
- **Clear Cache**: Delete WordPress transients starting with `stock_data_`
- **Disable Cache**: Set `stock_chart_cache_timeout` to `0` (not recommended)

---

## Troubleshooting

### All APIs Failing?
1. Check internet connectivity
2. Check if Yahoo Finance is accessible
3. Verify symbol format (JSL.NS for NSE, JSL.BO for BSE)
4. Check browser console for CORS errors
5. Plugin will fallback to sample data automatically

### Slow Loading?
1. Check cache is working (should be fast on subsequent requests)
2. Reduce cache timeout if data needs to be fresher
3. Consider using Alpha Vantage for faster responses

### Wrong Data?
1. Verify symbol is correct
2. Check exchange (NSE vs BSE)
3. Verify period mapping (1Y = 1y in Yahoo Finance)
