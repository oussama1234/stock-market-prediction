# Frontend Deployment Instructions

## Build
```bash
npm run build
```

## Deploy

### For Apache (.htaccess)
1. Copy the entire `dist/` folder to your web server
2. Ensure `.htaccess` file is included in the `dist/` folder
3. Make sure `mod_rewrite` is enabled:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```
4. Ensure `AllowOverride All` is set in your Apache config:
   ```apache
   <Directory /var/www/stockmarket>
       AllowOverride All
   </Directory>
   ```

### For Nginx
1. Copy the entire `dist/` folder to your web server
2. Update your Nginx site configuration with the config from `nginx.conf`
3. The key line is: `try_files $uri $uri/ /index.html;`
4. Test and reload Nginx:
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

## Example Nginx Configuration

```nginx
server {
    listen 80;
    server_name stockmarket.oussamameqqadmi.site;
    
    root /var/www/stockmarket/dist;
    index index.html;
    
    # SPA routing - THIS IS THE IMPORTANT PART
    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

## Troubleshooting 404 Errors

If you still get 404 errors on direct URL access:

### Check 1: Verify server type
```bash
# Check if Apache
apache2 -v

# Check if Nginx
nginx -v
```

### Check 2: For Apache, verify mod_rewrite
```bash
apache2ctl -M | grep rewrite
# Should show: rewrite_module (shared)
```

### Check 3: For Nginx, verify config
```bash
# Check current config
sudo nginx -T | grep try_files

# Should include: try_files $uri $uri/ /index.html;
```

### Check 4: Verify .htaccess is in place (Apache only)
```bash
ls -la /var/www/stockmarket/dist/.htaccess
```

### Check 5: Check permissions
```bash
# Make sure web server can read files
sudo chown -R www-data:www-data /var/www/stockmarket
sudo chmod -R 755 /var/www/stockmarket
```

## Common Issues

1. **404 on direct URL**: Server config not updated
2. **Blank page**: Check browser console for errors
3. **CORS errors**: API URL not configured correctly
4. **Assets not loading**: Check relative paths in build

## Environment Variables

Make sure `.env.production` has the correct API URL:
```
VITE_API_URL=https://apistock.oussamameqqadmi.site/api
```
