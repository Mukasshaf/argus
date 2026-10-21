#!/usr/bin/env bash
set -euo pipefail

ITERATIONS="${1:-100}"
VIOLATIONS=0

echo "=========================================================="
echo " Starting StayBridge Concurrency Race Stress Test ($ITERATIONS runs)"
echo " Invariant Check: booked == count(reservation_nights)"
echo "                   request mode available >= 0"
echo "=========================================================="

for i in $(seq 1 "$ITERATIONS"); do
  printf "Run %3d/%3d: " "$i" "$ITERATIONS"
  if ! php artisan test --filter RaceTest > /dev/null 2>&1; then
    echo "FAILED"
    VIOLATIONS=$((VIOLATIONS + 1))
  else
    echo "PASSED (0 invariant violations)"
  fi
done

echo "=========================================================="
echo " Stress Test Finished. Total Violations: $VIOLATIONS"
echo "=========================================================="

if [ "$VIOLATIONS" -gt 0 ]; then
  exit 1
fi
exit 0
