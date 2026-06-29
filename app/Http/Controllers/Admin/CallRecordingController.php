<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use App\Services\Calls\CallRecordingService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CallRecordingController extends Controller
{
    use ResolvesAdminAccount;

    public function play(
        Request $request,
        CallRecording $recording,
        CallRecordingService $recordings,
    ): StreamedResponse {
        $this->resolveAdminAccount($request);

        abort_unless($recording->storage_path, 404);
        abort_if($recordings->isExpired($recording), 410, 'Recording retention period has expired.');

        $disk = $recordings->playbackDisk();

        return response()->stream(function () use ($disk, $recording): void {
            $stream = \Illuminate\Support\Facades\Storage::disk($disk)->readStream($recording->storage_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="recording-'.$recording->id.'.mp3"',
        ]);
    }
}
