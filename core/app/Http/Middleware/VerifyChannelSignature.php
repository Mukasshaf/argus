<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Channel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyChannelSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check body size (max 1 MB = 1048576 bytes)
        $rawBody = (string) $request->getContent();
        if (strlen($rawBody) > 1048576) {
            return response()->json(['error' => 'Payload Too Large'], 413);
        }

        // 2. Read Channel from route parameter or header
        $channelCode = $request->route('channel') ?? $request->header('X-Channel-Code');
        $channel = Channel::where('code', $channelCode)->first();

        if (!$channel || empty($channel->webhook_secret)) {
            return response()->json(['error' => 'Unauthorized channel'], 401);
        }

        // 3. Read X-Timestamp and X-Signature
        $timestamp = (string) $request->header('X-Timestamp');
        $signatureHeader = (string) $request->header('X-Signature');

        if (empty($timestamp) || empty($signatureHeader)) {
            return response()->json(['error' => 'Missing signature headers'], 401);
        }

        // Timestamp skew check: reject when skew exceeds 300 seconds (Section 7.6)
        $ts = (int) $timestamp;
        if (abs(time() - $ts) > 300) {
            return response()->json(['error' => 'Timestamp skew exceeds 300s'], 401);
        }

        // Parse signature (expected format: sha256=<hex> or raw hex)
        $expectedSignature = $signatureHeader;
        if (str_starts_with($signatureHeader, 'sha256=')) {
            $expectedSignature = substr($signatureHeader, 7);
        }

        $computedSignature = hash_hmac('sha256', $timestamp . '.' . $rawBody, (string) $channel->webhook_secret);

        if (!hash_equals($computedSignature, $expectedSignature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Attach channel to request
        $request->attributes->set('channel', $channel);

        return $next($request);
    }
}
