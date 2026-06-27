<?php

namespace App\Services\Calls;

use App\Models\CallRecording;
use App\Models\CallSession;

class CallRecordingService
{
    public function attachFromWebhook(CallSession $session, array $payload): CallRecording
    {
        $recordingSid = $payload['RecordingSid'] ?? null;
        $url = $payload['RecordingUrl'] ?? null;
        $duration = (int) ($payload['RecordingDuration'] ?? 0);
        $status = $payload['RecordingStatus'] ?? 'completed';

        $recording = $session->recordings()
            ->where('provider_recording_sid', $recordingSid)
            ->first();

        if ($recording) {
            $recording->update([
                'url' => $url ?: $recording->url,
                'duration_seconds' => $duration ?: $recording->duration_seconds,
                'status' => $status,
            ]);

            return $recording;
        }

        return $session->recordings()->create([
            'provider_recording_sid' => $recordingSid,
            'url' => $url,
            'duration_seconds' => $duration,
            'status' => $status,
        ]);
    }
}
