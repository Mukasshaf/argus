#!/usr/bin/env bash
set -euo pipefail

echo "==> Tearing down StayBridge AWS resources..."

AWS_REGION="${AWS_DEFAULT_REGION:-ap-south-1}"

if command -v aws >/dev/null 2>&1; then
  echo "Deleting SQS queues..."
  for queue in staybridge_sync staybridge_ingest staybridge_sync_dlq staybridge_ingest_dlq; do
    URL=$(aws sqs get-queue-url --queue-name "$queue" --region "$AWS_REGION" --output text 2>/dev/null || true)
    if [ -n "$URL" ]; then
      aws sqs delete-queue --queue-url "$URL" --region "$AWS_REGION"
      echo "Deleted queue $queue"
    fi
  done

  echo "Deleting CloudWatch Log Group..."
  aws logs delete-log-group --log-group-name "/staybridge/production" --region "$AWS_REGION" 2>/dev/null || true

  echo "Teardown of SQS and CloudWatch completed."
else
  echo "AWS CLI not detected. Skipping remote teardown."
fi

docker compose -f docker/compose.prod.yml down -v || true
echo "==> Teardown complete."
