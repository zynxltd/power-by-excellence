<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class IvrFlow extends Model
{
    use BelongsToAccount;

    public const STEP_TYPES = ['say', 'gather', 'redirect', 'hangup', 'route'];

    protected $fillable = [
        'account_id',
        'campaign_id',
        'name',
        'nodes',
        'entry_node',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function callSessions(): HasMany
    {
        return $this->hasMany(CallSession::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaultSteps(): array
    {
        return [
            'start' => [
                'type' => 'say',
                'message' => 'Welcome. Please hold while we connect you.',
                'next' => 'route',
            ],
            'route' => ['type' => 'route'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    public static function normalizeNodes(array $nodes): array
    {
        $normalized = [];

        foreach ($nodes as $id => $node) {
            if (! is_string($id) || $id === '' || ! is_array($node)) {
                continue;
            }

            $type = $node['type'] ?? 'say';
            if ($type === 'play') {
                $type = 'say';
            }

            $normalized[$id] = array_merge($node, ['type' => $type]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @return list<string>
     */
    public static function validateGraph(string $entryNode, array $nodes): array
    {
        $errors = [];

        if (! isset($nodes[$entryNode])) {
            $errors[] = "Entry node \"{$entryNode}\" does not exist in the step graph.";
        }

        foreach ($nodes as $id => $node) {
            $type = $node['type'] ?? '';

            if (! in_array($type, array_merge(self::STEP_TYPES, ['play']), true)) {
                $errors[] = "Step \"{$id}\" has invalid type \"{$type}\".";

                continue;
            }

            foreach (self::referencedNodes($node) as $target) {
                if ($target !== 'route' && ! isset($nodes[$target])) {
                    $errors[] = "Step \"{$id}\" references unknown step \"{$target}\".";
                }
            }

            if ($type === 'gather' && empty($node['prompt'])) {
                $errors[] = "Gather step \"{$id}\" requires a prompt.";
            }

            if ($type === 'say' && empty($node['message'])) {
                $errors[] = "Say step \"{$id}\" requires a message.";
            }

            if ($type === 'redirect' && empty($node['next'])) {
                $errors[] = "Redirect step \"{$id}\" requires a next step.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public static function referencedNodes(array $node): array
    {
        $refs = [];

        if (! empty($node['next']) && is_string($node['next'])) {
            $refs[] = $node['next'];
        }

        if (! empty($node['default_next']) && is_string($node['default_next'])) {
            $refs[] = $node['default_next'];
        }

        foreach ($node['branches'] ?? [] as $target) {
            if (is_string($target) && $target !== '') {
                $refs[] = $target;
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     */
    public static function assertValidGraph(string $entryNode, array $nodes): void
    {
        $errors = self::validateGraph($entryNode, $nodes);

        if ($errors !== []) {
            throw ValidationException::withMessages(['nodes' => $errors]);
        }
    }
}
