#!/usr/bin/env bash
set -euo pipefail

echo "==> Deploying StayBridge Channel Manager..."

if command -v aws >/dev/null 2>&1; then
  echo "==> Fetching parameters from AWS SSM Parameter Store..."
  AWS_REGION="${AWS_DEFAULT_REGION:-ap-south-1}"
  APP_KEY=$(aws ssm get-parameter --name "/staybridge/APP_KEY" --with-decryption --query "Parameter.Value" --output text --region "$AWS_REGION" 2>/dev/null || echo "")
  DB_PASSWORD=$(aws ssm get-parameter --name "/staybridge/DB_PASSWORD" --with-decryption --query "Parameter.Value" --output text --region "$AWS_REGION" 2>/dev/null || echo "")
fi

git pull origin main || true
docker compose -f docker/compose.prod.yml build
docker compose -f docker/compose.prod.yml up -d

echo "==> Running database migrations..."
docker compose -f docker/compose.prod.yml exec -T app php artisan migrate --force

echo "==> Caching Laravel config, routes, and views..."
docker compose -f docker/compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker/compose.prod.yml exec -T app php artisan route:cache
docker compose -f docker/compose.prod.yml exec -T app php artisan view:cache

echo "==> Deployment complete."
