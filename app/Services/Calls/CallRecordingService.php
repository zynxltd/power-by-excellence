<?php

namespace App\Services\Calls;

use App\Models\CallRecording;
use App\Models\CallSession;
use App\Support\Products\CallLogicProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CallRecordingService
{
    public function attachFromWebhook(CallSession $session, array $payload): CallRecording
    {
        $session->loadMissing('account');

        $recordingSid = $payload['RecordingSid'] ?? null;
        $providerUrl = $payload['RecordingUrl'] ?? null;
        $duration = (int) ($payload['RecordingDuration'] ?? 0);
        $status = $payload['RecordingStatus'] ?? 'completed';

        $recording = $session->recordings()
            ->where('provider_recording_sid', $recordingSid)
            ->first();

        if ($recording) {
            $recording->update([
                'url' => $providerUrl ?: $recording->url,
                'duration_seconds' => $duration ?: $recording->duration_seconds,
                'status' => $status,
            ]);
        } else {
            $recording = $session->recordings()->create([
                'provider_recording_sid' => $recordingSid,
                'url' => $providerUrl,
                'duration_seconds' => $duration,
                'status' => $status,
            ]);
        }

        if ($status === 'completed' && $providerUrl && ! $recording->storage_path) {
            $this->downloadAndStore($recording->fresh(), $session, $providerUrl);
        }

        return $recording->fresh();
    }

    public function downloadAndStore(CallRecording $recording, CallSession $session, string $providerUrl): CallRecording
    {
        $disk = config('telephony.recording_disk', 'local');
        $path = sprintf(
            'call-recordings/%d/%d/%s.mp3',
            $session->account_id,
            $session->id,
            $recording->provider_recording_sid ?? $recording->id,
        );

        $downloadUrl = str_ends_with($providerUrl, '.mp3') ? $providerUrl : $providerUrl.'.mp3';

        try {
            $request = Http::timeout(30);
            $sid = config('telephony.twilio.sid');
            $token = config('telephony.twilio.token');

            if ($sid && $token) {
                $request = $request->withBasicAuth($sid, $token);
            }

            $response = $request->get($downloadUrl);

            if (! $response->successful()) {
                Log::warning('call_recording.download_failed', [
                    'recording_id' => $recording->id,
                    'status' => $response->status(),
                ]);

                return $recording;
            }

            Storage::disk($disk)->put($path, $response->body());

            $retentionDays = (int) (CallLogicProduct::settings($session->account)['recording_retention_days']
                ?? config('telephony.recording_retention_days', 90));

            $recording->update([
                'storage_path' => $path,
                'retention_expires_at' => now()->addDays(max(1, $retentionDays)),
                'status' => 'stored',
            ]);
        } catch (\Throwable $e) {
            Log::error('call_recording.store_failed', [
                'recording_id' => $recording->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $recording->fresh();
    }

    public function playbackDisk(): string
    {
        return config('telephony.recording_disk', 'local');
    }

    public function isExpired(CallRecording $recording): bool
    {
        return $recording->retention_expires_at !== null
            && $recording->retention_expires_at->isPast();
    }
}
