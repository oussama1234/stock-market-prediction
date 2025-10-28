# Production Deployment Guide for CyberPanel VPS

## Server Information
- **Backend API**: https://apistock.oussamameqqadmi.site
- **Frontend**: https://stockmarket.oussamameqqadmi.site
- **Database**: ouss_stockmarket
- **DB User**: ouss_stockmarket
- **DB Password**: 7Gx7OPkxE#q1Ge03

## Prerequisites

### Server Requirements
- CyberPanel installed
- PHP 8.1+ with extensions: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- MySQL/MariaDB
- Python 3.8+
- Node.js 18+ (for building frontend)
- Composer
- Git

### Python Dependencies
```bash
pip3 install numpy pandas scikit-learn
```

## Backend Deployment

### 1. Upload Backend Files
```bash
# On your VPS, navigate to the backend directory
cd /home/apistock.oussamameqqadmi.site/public_html

# If uploading via Git
git clone https://github.com/oussama1234/stock-market-prediction.git temp
cp -r temp/backend/* .
rm -rf temp

# Or upload files via SFTP/FTP to /home/apistock.oussamameqqadmi.site/public_html
```

### 2. Set Up Environment
```bash
# Copy production environment file
cp .env.production .env

# Install Composer dependencies (production only)
composer install --no-dev --optimize-autoloader

# Generate application key (if not set)
php artisan key:generate

# Set correct permissions
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R nobody:nobody storage bootstrap/cache
```

### 3. Database Setup
```bash
# Run migrations
php artisan migrate --force

# Seed initial data (if needed)
php artisan db:seed --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Set Up Python
```bash
# Make sure Python scripts are executable
chmod +x python/models/*.py
chmod +x python/tests/*.py

# Test Python model
python3 python/models/quick_model_v6.py predict --features '{"close":100,"rsi_14":50}'
```

### 5. Configure Web Server (CyberPanel/OpenLiteSpeed)

Create/Edit `.htaccess` in public_html:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Redirect to public folder
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ /public/$1 [L]
</IfModule>
```

Create `.htaccess` in public folder:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Redirect Trailing Slashes
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]
    
    # Send Requests To Front Controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### 6. SSL Certificate
```bash
# In CyberPanel, go to:
# Websites > List Websites > apistock.oussamameqqadmi.site > Manage SSL
# Click "Issue SSL" to get Let's Encrypt certificate
```

### 7. Cron Jobs (Optional - for scheduled tasks)
```bash
# Add to crontab
* * * * * cd /home/apistock.oussamameqqadmi.site/public_html && php artisan schedule:run >> /dev/null 2>&1
```

## Frontend Deployment

### 1. Build Frontend Locally
```bash
# On your local machine
cd frontend

# Install dependencies
npm install

# Build for production
npm run build

# This creates a 'dist' folder with optimized static files
```

### 2. Upload Frontend Files
```bash
# Upload the contents of 'frontend/dist' folder to:
# /home/stockmarket.oussamameqqadmi.site/public_html/

# Via SFTP or:
scp -r dist/* user@your-server:/home/stockmarket.oussamameqqadmi.site/public_html/
```

### 3. Configure Frontend Web Server

Create `.htaccess` in public_html:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Redirect to HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # Handle SPA routing
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Gzip Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Browser Caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/json "access plus 1 week"
</IfModule>
```

### 4. SSL Certificate
```bash
# In CyberPanel, go to:
# Websites > List Websites > stockmarket.oussamameqqadmi.site > Manage SSL
# Click "Issue SSL" to get Let's Encrypt certificate
```

## CORS Configuration

In `backend/config/cors.php`, ensure:
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://stockmarket.oussamameqqadmi.site',
        'http://stockmarket.oussamameqqadmi.site',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

## Post-Deployment Checklist

### Backend
- [ ] .env.production copied to .env
- [ ] Database credentials correct
- [ ] Migrations run successfully
- [ ] Python scripts executable and working
- [ ] Storage permissions set (755/775)
- [ ] Composer dependencies installed (production)
- [ ] Config/route/view cached
- [ ] SSL certificate issued and working
- [ ] API endpoints responding (test: /api/health)

### Frontend
- [ ] Built with production .env
- [ ] All files uploaded to public_html
- [ ] .htaccess configured
- [ ] SSL certificate issued and working
- [ ] SPA routing works (refresh on any page)
- [ ] API calls working (check browser console)
- [ ] No hardcoded localhost URLs

## Testing

### Backend API
```bash
# Test health endpoint
curl https://apistock.oussamameqqadmi.site/api/health

# Test prediction endpoint
curl -X POST https://apistock.oussamameqqadmi.site/api/predictions/predict \
  -H "Content-Type: application/json" \
  -d '{"symbol":"AAPL","horizon":"today","model":"v6"}'
```

### Frontend
1. Open https://stockmarket.oussamameqqadmi.site
2. Check browser console for errors
3. Test stock search and prediction features
4. Verify API calls go to https://apistock.oussamameqqadmi.site

## Troubleshooting

### 500 Internal Server Error
- Check storage permissions: `chmod -R 775 storage bootstrap/cache`
- Check error logs: `tail -f storage/logs/laravel.log`
- Clear cache: `php artisan cache:clear && php artisan config:clear`

### Database Connection Failed
- Verify credentials in .env
- Check if MySQL is running
- Test connection: `php artisan tinker` then `DB::connection()->getPdo();`

### Python Script Errors
- Check Python path in .env: `PYTHON_EXECUTABLE=/usr/bin/python3`
- Test Python: `python3 --version`
- Test model: `python3 python/models/quick_model_v6.py predict --features '{}'`

### CORS Errors
- Check config/cors.php allowed_origins
- Clear config cache: `php artisan config:clear`
- Check headers in browser network tab

### Frontend Not Loading
- Check .htaccess in public_html
- Verify all dist files uploaded
- Check browser console for errors
- Verify VITE_API_URL in built files

## Maintenance

### Update Backend
```bash
cd /home/apistock.oussamameqqadmi.site/public_html
git pull origin main  # if using git
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Update Frontend
```bash
# Build locally
npm run build

# Upload dist folder to server
# Clear browser cache and test
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Backup Database
```bash
mysqldump -u ouss_stockmarket -p ouss_stockmarket > backup_$(date +%Y%m%d).sql
```

## Security Recommendations

1. **Never commit .env files to Git**
2. **Use strong APP_KEY** (32 character random string)
3. **Keep API keys secure**
4. **Regular backups** (database + files)
5. **Monitor error logs** regularly
6. **Update dependencies** periodically
7. **Use HTTPS only** (force redirect)
8. **Limit database user privileges**
9. **Set proper file permissions** (no 777)
10. **Enable firewall** (UFW/CSF)

## Support

For issues, check:
- Laravel logs: `storage/logs/laravel.log`
- Web server logs (CyberPanel/OpenLiteSpeed logs)
- Browser console (F12)
- Network tab for API calls

Good luck with your deployment! 🚀
