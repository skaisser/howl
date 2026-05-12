<?php

namespace Skaisser\Howl\Events;

/**
 * Fired when a deployment completes (success or failure).
 *
 * v0.2.0 contract: implements all 8 methods.
 *
 * ⚠️  BREAKING CHANGE from v0.1.0 — constructor argument order changed:
 *   v0.1.0: (string $version, string $commit, string $env, ?int $durationSeconds)
 *   v0.2.0: (string $version, string $env, string $commit, ...)
 *   env now comes SECOND; commit comes THIRD.
 *
 * Constructor:
 *   string  $version          — semantic version tag (e.g. "v1.4.2")
 *   string  $env              — target environment (e.g. "production", "staging")
 *   string  $commit           — full or short commit SHA
 *   ?string $branch           — source branch (optional, defaults shown as "main")
 *   ?int    $durationSeconds  — how long the deployment took, in seconds
 *   array   $links            — optional Discord embed links
 *   array   $meta             — optional metadata
 */
class DeploymentEvent extends HowlEvent
{
    public function __construct(
        protected readonly string $version,
        protected readonly string $env,
        protected readonly string $commit,
        protected readonly ?string $branch = null,
        protected readonly ?int $durationSeconds = null,
        array $links = [],
        array $meta = [],
    ) {
        parent::__construct($links, $meta);
    }

    public function severity(): string
    {
        return 'deployment';
    }

    public function emoji(): string
    {
        return '🚀';
    }

    public function title(): string
    {
        return "Deployed {$this->version} to {$this->env}";
    }

    public function description(): string
    {
        $branch = $this->branch ?? 'main';

        return "Version {$this->version} shipped on {$branch}.";
    }

    public function fields(): array
    {
        $fields = [
            ['name' => '🚀 Version', 'value' => $this->version,              'inline' => true],
            ['name' => '🌐 Env',     'value' => $this->env,                  'inline' => true],
            ['name' => '🔗 Commit',  'value' => substr($this->commit, 0, 7), 'inline' => true],
        ];

        if ($this->branch !== null) {
            $fields[] = ['name' => '🌿 Branch', 'value' => $this->branch, 'inline' => true];
        }

        if ($this->durationSeconds !== null) {
            $fields[] = ['name' => '⏱️ Duration', 'value' => $this->formatDuration($this->durationSeconds), 'inline' => true];
        }

        return $fields;
    }

    public function codeBlocks(): array
    {
        return [];
    }

    public function footerMeta(): array
    {
        return [
            'version' => $this->version,
            'env' => $this->env,
            'commit_short' => substr($this->commit, 0, 7),
        ];
    }

    public function channel(): ?string
    {
        return 'deployments';
    }

    // -------------------------------------------------------------------------
    // Private helper
    // -------------------------------------------------------------------------

    /**
     * Format a duration in seconds as a human-readable string.
     * e.g. 42 → "42s", 65 → "1m 5s"
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        return $remaining > 0 ? "{$minutes}m {$remaining}s" : "{$minutes}m";
    }
}
