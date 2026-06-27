<?php

namespace App\Services\Messaging;

use App\Models\MessageSend;

class EmailTrackingService
{
    public function wrapHtml(string $html, MessageSend $send): string
    {
        $html = $this->wrapLinks($html, $send);
        $pixel = route('messaging.track.open', $send->token);

        if (stripos($html, '</body>') !== false) {
            return str_ireplace('</body>', "<img src=\"{$pixel}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none\" /></body>", $html);
        }

        return $html."<img src=\"{$pixel}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none\" />";
    }

    public function wrapLinks(string $html, MessageSend $send): string
    {
        return preg_replace_callback(
            '/href=(["\'])(https?:\/\/[^"\']+)\1/i',
            function (array $matches) use ($send) {
                $url = $matches[2];
                $tracked = route('messaging.track.click', [
                    'token' => $send->token,
                    'url' => base64_encode($url),
                ]);

                return 'href='.$matches[1].$tracked.$matches[1];
            },
            $html,
        ) ?? $html;
    }

    public function buildHtmlEmail(string $plainBody, ?string $htmlBody, MessageSend $send): string
    {
        $html = $htmlBody ?: nl2br(e($plainBody));
        $unsubscribe = route('messaging.unsubscribe', $send->token);

        $footer = '<p style="font-size:12px;color:#666;margin-top:24px;">'
            .'<a href="'.$unsubscribe.'">Unsubscribe</a></p>';

        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $footer.'</body>', $html);
        } else {
            $html .= $footer;
        }

        return $this->wrapHtml($html, $send);
    }
}
