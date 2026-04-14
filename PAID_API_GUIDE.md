# Paid API Guide for Stock Chart Plugin

## Current API Status

### ✅ **Yahoo Finance (Free - Primary)**
- **Status**: Active and working
- **API Key Required**: ❌ No
- **Cost**: Free
- **Rate Limits**: None (reasonable use)
- **Reliability**: High

### ⚠️ **Alpha Vantage (Paid Premium Available - Fallback)**
- **Status**: Requires API key configuration
- **API Key Required**: ✅ Yes (for paid plans)
- **Free Tier Available**: Yes (5 calls/min, 500 calls/day)
- **Cost**: Free tier available, Premium from $49.99/month
- **Rate Limits**: Depends on plan
- **Reliability**: High (premium plans)

---

## Where to Get Paid APIs

### 1. Alpha Vantage Premium

**Website**: https://www.alphavantage.co/premium/

**Free Tier** (No credit card required):
- Get free API key: https://www.alphavantage.co/support/#api-key
- 5 API calls per minute
- 500 API calls per day
- Good for testing and low-traffic sites

**Premium Plans**:

| Plan | Price/Month | API Calls/Min | API Calls/Day | Best For |
|------|-------------|---------------|---------------|----------|
| **Starter** | $49.99 | 75 | 1,200 | Small businesses |
| **Growth** | $149.99 | 120 | 5,000 | Growing businesses |
| **Pro** | $249.99 | 300 | 15,000 | High-traffic sites |
| **Enterprise** | Custom | Custom | Custom | Large enterprises |

**Benefits of Premium**:
- Higher rate limits
- Better data quality
- Priority support
- More reliable uptime
- Advanced features

**How to Get**:
1. Visit https://www.alphavantage.co/premium/
2. Choose your plan
3. Sign up and get API key
4. Add API key in WordPress Admin → Settings → Stock Chart

---

### 2. Yahoo Finance (No Paid Option)

Yahoo Finance API is **free** and doesn't offer paid plans. It's the primary API used by the plugin.

**Note**: Yahoo Finance may have occasional rate limiting or downtime, which is why Alpha Vantage is available as a fallback.

---

## How to Configure API Key

### Step 1: Get Your API Key

**For Free Tier**:
1. Go to https://www.alphavantage.co/support/#api-key
2. Fill out the form (name, email)
3. Get your free API key instantly

**For Premium Plans**:
1. Go to https://www.alphavantage.co/premium/
2. Choose a plan and sign up
3. Get your premium API key

### Step 2: Add API Key in WordPress

1. Go to **WordPress Admin Dashboard**
2. Navigate to **Settings → Stock Chart**
3. Enter your API key in the "Alpha Vantage API Key" field
4. Click **Save Changes**

### Step 3: Verify API Key

- The settings page will show:
  - ✅ **Green checkmark** if API key is set
  - ⚠️ **Warning** if no API key is set

---

## Current API Fallback Process

```
1. Yahoo Finance (Free)
   ↓ (if fails)
2. Alpha Vantage (if API key is set)
   ↓ (if fails)
```

---

## API Key Usage Check

### Is API Key Currently Being Used?

**Check in WordPress Admin**:
1. Go to **Settings → Stock Chart**
2. Look at the "Alpha Vantage API Key" field
3. If it shows "⚠ No API key set" → API key is NOT being used
4. If it shows "✓ API Key is set" → API key IS configured

**Check in Code**:
- The plugin checks: `get_option('stock_chart_api_key', 'demo')`
- If value is empty, 'demo', or not set → Alpha Vantage is skipped
- If value is a valid API key → Alpha Vantage is used as fallback

**Current Status**: 
- Check your WordPress admin settings to see if API key is configured
- Default value is `'demo'` which means Alpha Vantage is **NOT active**

---

## Recommendations

### For Low Traffic Sites:
- ✅ Use **Yahoo Finance** (free, already active)
- Optional: Get **Alpha Vantage free tier** as backup

### For Medium Traffic Sites:
- ✅ Use **Yahoo Finance** (primary)
- ✅ Get **Alpha Vantage Starter Plan** ($49.99/month) as backup

### For High Traffic Sites:
- ✅ Use **Yahoo Finance** (primary)
- ✅ Get **Alpha Vantage Growth or Pro Plan** ($149.99-$249.99/month) as backup

---

## Testing Your API Key

After adding your API key:

1. **Check Settings Page**: Should show "✓ API Key is set"
2. **Test Chart**: Load a stock chart on your site
3. **Check Browser Console**: Look for API calls in Network tab
4. **Check Response**: The AJAX response includes `"source": "Alpha Vantage"` if Alpha Vantage is used

---

## Troubleshooting

### API Key Not Working?
1. Verify API key is correct (no extra spaces)
2. Check if API key is saved in WordPress options
3. Test API key directly: `https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=JSL.NS&apikey=YOUR_KEY`
4. Check WordPress debug log for errors

### Still Using Yahoo Finance?
- That's normal! Yahoo Finance is tried first
- Alpha Vantage is only used if Yahoo Finance fails
- This is by design for cost optimization

### Need to Force Alpha Vantage?
- Currently not possible - Yahoo Finance is always tried first
- This ensures free API is used when available
- Alpha Vantage acts as a paid backup

---

## Cost Comparison

| API | Free Tier | Paid Plans | Best For |
|-----|-----------|------------|----------|
| **Yahoo Finance** | ✅ Free | ❌ None | All sites |
| **Alpha Vantage** | ✅ Free (limited) | ✅ $49.99-$249.99/month | Backup/High traffic |

---

## Summary

- **Currently Using**: Yahoo Finance (free) - ✅ Active
- **API Key Status**: Check WordPress Admin → Settings → Stock Chart
- **Paid API Available**: Alpha Vantage Premium (from $49.99/month)
- **Where to Get**: https://www.alphavantage.co/premium/
- **Free Tier Available**: Yes, at https://www.alphavantage.co/support/#api-key
