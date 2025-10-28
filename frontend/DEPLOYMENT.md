# Frontend Deployment Instructions

## Build
```bash
npm run build
```

## Deploy for LiteSpeed

1. **Copy the entire `dist/` folder to your web server**
   ```bash
   scp -r dist/* user@server:/path/to/public_html/
   ```

2. **Verify `.htaccess` is in place**
   ```bash
   ls -la /path/to/public_html/.htaccess
   ```
   The `.htaccess` file should already be in the `dist/` folder after build.

3. **That's it!** LiteSpeed automatically reads `.htaccess` files

## How it works

The `.htaccess` file redirects all non-file requests to `index.html`, allowing React Router to handle routing:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ index.html [L]
</IfModule>
```

## Troubleshooting 404 Errors on LiteSpeed

If you still get 404 errors on direct URL access:

### Check 1: Verify `.htaccess` exists
```bash
ls -la /path/to/public_html/.htaccess
```

### Check 2: Check `.htaccess` content
Make sure it contains:
```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ index.html [L]
</IfModule>
```

### Check 3: Verify file permissions
```bash
chmod 644 /path/to/public_html/.htaccess
chmod 755 /path/to/public_html
```

### Check 4: Clear LiteSpeed cache
From LiteSpeed admin panel or via command:
```bash
# Clear LiteSpeed cache
sudo systemctl restart lsws
```

### Check 5: Rebuild and redeploy
```bash
cd frontend
npm run build
# Then copy dist/ to server again
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
