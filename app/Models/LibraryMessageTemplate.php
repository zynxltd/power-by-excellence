<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LibraryMessageTemplate extends Model
{
    protected $fillable = [
        'vertical_id',
        'channel',
        'name',
        'subject',
        'body',
        'html_body',
        'preview_data',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'preview_data' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function cloneToAccount(int $accountId, ?string $name = null): MessageTemplate
    {
        return MessageTemplate::create([
            'account_id' => $accountId,
            'name' => $name ?? $this->name,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body' => $this->body,
            'html_body' => $this->html_body,
            'preview_data' => $this->preview_data,
        ]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeFiltered(Builder $query, ?string $verticalId = null, ?string $channel = null): Builder
    {
        if ($verticalId) {
            $query->where('vertical_id', $verticalId);
        }

        if ($channel) {
            $query->where('channel', $channel);
        }

        return $query;
    }
}
