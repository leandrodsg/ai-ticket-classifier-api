# Deployment Guide - Railway

This guide covers deploying the AI Ticket Classifier API to Railway using **FrankenPHP** as the application server.

**Why FrankenPHP?**
- Modern PHP application server built on Caddy
- Native HTTP/2 and HTTP/3 support
- Worker mode for optimal performance (4 workers by default)
- Much better than `php artisan serve` (development only)
- Production-ready with built-in concurrency

---

## Pre-Deployment Checklist

### 1. Generate Secret Keys

**Generate APP_KEY:**
```bash
php artisan key:generate --show
```
Copy the output (e.g., `base64:abc123...`)

**Generate CSV_SIGNING_KEY:**
```bash
php -r "echo bin2hex(random_bytes(32));"
```
Copy the output (64 character hex string)

### 2. Database Setup

**Option A: Railway PostgreSQL (Recommended)**
1. In Railway dashboard, add PostgreSQL plugin
2. Railway will auto-create `DATABASE_URL`
3. No manual configuration needed

**Option B: External Database (Supabase, etc.)**
1. Create PostgreSQL database
2. Get connection string
3. Set as `DATABASE_URL` in Railway

### 3. Configure Environment Variables

In Railway dashboard, set these variables:

**Required:**
```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your_generated_key_here
CSV_SIGNING_KEY=your_64_char_hex_key_here
OPENROUTER_API_KEY=sk-or-v1-your-key-here
DATABASE_URL=postgresql://user:pass@host:5432/db
```

**Optional (with defaults):**
```bash
APP_TIMEZONE=UTC
LOG_CHANNEL=stack
LOG_LEVEL=error
CACHE_DRIVER=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

### 4. CORS Configuration (if needed)

If you have a frontend:
```bash
FRONTEND_URL=https://your-frontend.com
```

---

## Deployment Steps

### 1. Connect Repository
1. Go to Railway dashboard
2. New Project → Deploy from GitHub
3. Select your repository
4. Railway will auto-detect Laravel

### 2. Configure Build
Railway will use `railway.toml` automatically:
- Uses Dockerfile for build (FrankenPHP application server)
- Starts with 4 worker processes for optimal concurrency
- Health check on `/api/health`
- **Note:** FrankenPHP is production-ready (unlike `php artisan serve`)

### 3. Run Migrations
After first deploy:
```bash
railway run php artisan migrate --force
```

Or in Railway dashboard → your service → Settings → Deploy → Add command:
```bash
php artisan migrate --force
```

### 4. Verify Deployment
Test the health endpoint:
```bash
curl https://your-app.railway.app/api/health
```

Expected response:
```json
{
  "status": "healthy",
  "timestamp": "2025-12-29T10:00:00.000000Z",
  "environment": "production"
}
```

### 5. Test API Endpoints

**Generate CSV:**
```bash
curl -X POST https://your-app.railway.app/api/csv/generate \
  -H "Content-Type: application/json" \
  -d '{"ticket_count": 5}'
```

**Get System Info:**
```bash
curl https://your-app.railway.app/api/info
```

---

## Post-Deployment Monitoring

### First 24 Hours
Monitor logs in Railway dashboard:
- Check for PHP errors
- Verify database connections
- Monitor API response times
- Check rate limiting behavior

### Performance Metrics
- Health check response: < 2s
- CSV generation (5 tickets): < 5s
- Upload processing (50 tickets): < 30s

---

## Rollback Procedure

### If Deployment Fails:

**1. Immediate Rollback (Railway)**
```bash
# In Railway dashboard:
Deployments → Select previous successful deployment → Rollback
```

**2. Database Rollback (if schema changed)**
If you ran migrations that broke things:
```bash
# Connect to database
railway run php artisan migrate:rollback --step=1
```

**3. Verify Health**
```bash
curl https://your-app.railway.app/api/health
```

### If Database is Corrupted:
1. Restore from Railway automatic backup
2. Or recreate database and re-run migrations
3. Data loss expected (classification jobs are ephemeral)

---

## Troubleshooting

### Issue: 500 Internal Server Error
**Check:**
- Railway logs for PHP errors
- `APP_KEY` is set correctly
- Database connection is valid

**Fix:**
```bash
railway logs
```

### Issue: Health Check Failing
**Check:**
- `/api/health` endpoint returns 200
- Response time < 30s
- Database is reachable

**Fix:**
```bash
# Test locally with production config
APP_ENV=production php artisan serve
curl http://localhost:8000/api/health
```

### Issue: Migrations Fail
**Check:**
- Database credentials are correct
- PostgreSQL version is 14+
- User has CREATE TABLE permissions

**Fix:**
```bash
# Test connection
railway run php artisan db:show

# Reset migrations (WARNING: deletes data)
railway run php artisan migrate:fresh --force
```

### Issue: Rate Limiting Too Strict
**Check:**
- Rate limit headers in response
- Redis/cache is working

**Temporary Fix:**
Adjust in `.env`:
```bash
RATE_LIMIT_UPLOAD=50
RATE_LIMIT_GENERATE=20
```

---

## Scaling Considerations

### Horizontal Scaling (Multiple Replicas)
Railway supports multiple instances with FrankenPHP:
1. Add more replicas in Railway dashboard
2. Each replica runs 4 workers (total: replicas × 4 workers)
3. Database connection pooling is automatically handled
4. No additional configuration needed

### Vertical Scaling (Bigger Instances)
If response times are slow:
1. Increase Railway instance size (more CPU/RAM)
2. FrankenPHP workers will utilize additional resources
3. Monitor OpenRouter API latency (main bottleneck)
4. Consider adding queue for async processing (future enhancement)

### Worker Tuning
Adjust FrankenPHP workers in `railway.toml` if needed:
```toml
startCommand = "frankenphp php-server --workers 8 --listen :$PORT"
```
**Recommendation:** Start with 4 workers, increase to 8 if needed

---

## Backup Strategy

### Automated (Railway)
- Daily database backups (automatic)
- 7-day retention

### Manual Backup
```bash
# Export database
railway run pg_dump > backup-$(date +%Y%m%d).sql

# Restore
railway run psql < backup-20251229.sql
```

---