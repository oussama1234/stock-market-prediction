#!/bin/bash

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Stock Market Prediction - Deployment Prep${NC}"
echo -e "${GREEN}========================================${NC}"

# Check if we're in the project root
if [ ! -d "backend" ] || [ ! -d "frontend" ]; then
    echo -e "${RED}Error: Must run from project root directory${NC}"
    exit 1
fi

# Backend Preparation
echo -e "\n${YELLOW}[1/5] Preparing Backend...${NC}"
cd backend

# Copy production environment
if [ -f ".env.production" ]; then
    echo "✓ Found .env.production"
else
    echo -e "${RED}✗ Missing .env.production${NC}"
    exit 1
fi

# Copy production htaccess
if [ -f ".htaccess.production" ]; then
    cp .htaccess.production .htaccess
    echo "✓ Copied production .htaccess"
fi

# Check composer.json
if [ -f "composer.json" ]; then
    echo "✓ Found composer.json"
else
    echo -e "${RED}✗ Missing composer.json${NC}"
    exit 1
fi

cd ..

# Frontend Preparation
echo -e "\n${YELLOW}[2/5] Preparing Frontend...${NC}"
cd frontend

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "Installing npm dependencies..."
    npm install
fi

# Copy production environment
if [ -f ".env.production" ]; then
    echo "✓ Found .env.production"
else
    echo -e "${RED}✗ Missing .env.production${NC}"
    exit 1
fi

# Build frontend
echo -e "\n${YELLOW}[3/5] Building Frontend for Production...${NC}"
npm run build

if [ -d "dist" ]; then
    echo -e "${GREEN}✓ Frontend built successfully${NC}"
    echo "  Build output: frontend/dist"
else
    echo -e "${RED}✗ Frontend build failed${NC}"
    exit 1
fi

# Copy production htaccess to dist
if [ -f ".htaccess.production" ]; then
    cp .htaccess.production dist/.htaccess
    echo "✓ Copied production .htaccess to dist"
fi

cd ..

# Create deployment package
echo -e "\n${YELLOW}[4/5] Creating Deployment Package...${NC}"

# Create deployment directory
DEPLOY_DIR="deployment_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$DEPLOY_DIR"

# Copy backend files (excluding node_modules, vendor, etc.)
echo "Copying backend files..."
rsync -av --exclude='node_modules' \
         --exclude='vendor' \
         --exclude='storage/logs/*' \
         --exclude='storage/framework/cache/*' \
         --exclude='storage/framework/sessions/*' \
         --exclude='storage/framework/views/*' \
         --exclude='.git' \
         --exclude='.env' \
         --exclude='.env.docker' \
         backend/ "$DEPLOY_DIR/backend/"

# Copy .env.production as .env
cp backend/.env.production "$DEPLOY_DIR/backend/.env"

# Copy frontend dist
echo "Copying frontend build..."
cp -r frontend/dist "$DEPLOY_DIR/frontend"

# Create README
cat > "$DEPLOY_DIR/README.txt" << EOF
Stock Market Prediction - Deployment Package
Generated: $(date)

CONTENTS:
- backend/   : Laravel backend application
- frontend/  : Built React frontend (static files)

DEPLOYMENT INSTRUCTIONS:
1. Upload backend/* to: /home/apistock.oussamameqqadmi.site/public_html/
2. Upload frontend/* to: /home/stockmarket.oussamameqqadmi.site/public_html/

See DEPLOYMENT_GUIDE.md in project root for detailed instructions.

IMPORTANT:
- Backend .env is already configured for production
- Run 'composer install --no-dev --optimize-autoloader' on server
- Run 'php artisan migrate --force' on server
- Set storage permissions: chmod -R 775 storage bootstrap/cache
EOF

echo -e "${GREEN}✓ Deployment package created: $DEPLOY_DIR${NC}"

# Summary
echo -e "\n${YELLOW}[5/5] Deployment Summary${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "Backend ready:  ${GREEN}✓${NC}"
echo -e "Frontend built: ${GREEN}✓${NC}"
echo -e "Package ready:  ${GREEN}✓${NC}"
echo -e "\n📦 Deployment package: ${GREEN}$DEPLOY_DIR${NC}"
echo -e "\n${YELLOW}Next Steps:${NC}"
echo "1. Review DEPLOYMENT_GUIDE.md"
echo "2. Upload files to your VPS"
echo "3. Run setup commands on server"
echo "4. Test your application"
echo -e "\n${GREEN}Good luck with your deployment! 🚀${NC}"
