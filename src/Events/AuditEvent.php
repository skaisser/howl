<?php

namespace Skaisser\Howl\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired for auditable state changes (access, permission, data mutations).
 *
 * v0.2.0 contract: implements all 8 methods; codeBlocks() emits a
 * '🔄 Diff' entry when before !== after.
 *
 * Constructor:
 *   string $actor    — who performed the action (user email, system name, etc.)
 *   string $action   — what was done (e.g. "updated", "deleted", "granted")
 *   mixed  $target   — what was affected (Eloquent model, scalar, array, object)
 *   array  $before   — state before the action
 *   array  $after    — state after the action
 *   array  $links    — optional Discord embed links
 *   array  $meta     — optional metadata
 */
class AuditEvent extends HowlEvent
{
    public function __construct(
        protected readonly string $actor,
        protected readonly string $action,
        protected readonly mixed $target,
        protected readonly array $before = [],
        protected readonly array $after = [],
        array $links = [],
        array $meta = [],
    ) {
        parent::__construct($links, $meta);
    }

    public function severity(): string
    {
        return 'audit';
    }

    public function emoji(): string
    {
        return '🔒';
    }

    public function title(): string
    {
        return "Audit: {$this->action}";
    }

    public function description(): string
    {
        return "Actor {$this->actor} performed {$this->action} on {$this->targetSummary()}.";
    }

    public function fields(): array
    {
        return [
            ['name' => '👤 Actor',   'value' => $this->actor,              'inline' => true],
            ['name' => '⚡ Action',  'value' => $this->action,             'inline' => true],
            ['name' => '🎯 Target',  'value' => $this->targetSummary(),    'inline' => true],
        ];
    }

    public function codeBlocks(): array
    {
        if ($this->before === $this->after) {
            return [];
        }

        return [
            [
                'name' => '🔄 Diff',
                'code' => json_encode(
                    ['before' => $this->before, 'after' => $this->after],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ),
                'lang' => 'json',
            ],
        ];
    }

    public function footerMeta(): array
    {
        return [
            'actor' => $this->actor,
            'action' => $this->action,
            'target_class' => is_object($this->target)
                ? get_class($this->target)
                : gettype($this->target),
        ];
    }

    public function channel(): ?string
    {
        return 'audit';
    }

    // -------------------------------------------------------------------------
    // Private helper
    // -------------------------------------------------------------------------

    /**
     * Produce a short human-readable label for $target.
     *
     *  - Eloquent model → "ClassName:id"
     *  - Scalar         → cast to string
     *  - Array / object → JSON-encoded (compact)
     */
    private function targetSummary(): string
    {
        if ($this->target instanceof Model) {
            return class_basename($this->target).':'.$this->target->getKey();
        }

        if (is_scalar($this->target)) {
            return (string) $this->target;
        }

        return (string) json_encode($this->target);
    }
}
