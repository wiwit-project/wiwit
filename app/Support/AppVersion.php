<?php

namespace App\Support;

/**
 * Build metadata for the running deployment.
 */
class AppVersion
{
    public function __construct(
        public readonly ?string $releaseTag = null,
        public readonly ?string $refName = null,
        public readonly ?string $commitSha = null,
        public readonly ?string $repositoryUrl = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            releaseTag: self::clean(config('app.release_tag')),
            refName: self::clean(config('app.ref_name')),
            commitSha: self::clean(config('app.commit_sha')),
            repositoryUrl: self::clean(config('app.repository_url')),
        );
    }

    /**
     * Human readable build label, or null when the build was not stamped.
     */
    public function displayString(): ?string
    {
        if (filled($this->releaseTag)) {
            return $this->releaseTag;
        }

        if (filled($this->refName) && filled($this->commitSha)) {
            return 'Ver. '.$this->refName.'@'.substr($this->commitSha, 0, 7);
        }

        return null;
    }

    /**
     * Canonical link to the running build.
     */
    public function url(): ?string
    {
        if (blank($this->repositoryUrl)) {
            return null;
        }

        $repository = rtrim($this->repositoryUrl, '/');

        return match (true) {
            filled($this->releaseTag) => $repository.'/releases/tag/'.rawurlencode($this->releaseTag),
            filled($this->commitSha) => $repository.'/commit/'.rawurlencode($this->commitSha),
            default => null,
        };
    }

    /**
     * @return array{display: ?string, release_tag: ?string, ref_name: ?string, commit_sha: ?string, repository_url: ?string}
     */
    public function toArray(): array
    {
        return [
            'display' => $this->displayString(),
            'release_tag' => $this->releaseTag,
            'ref_name' => $this->refName,
            'commit_sha' => $this->commitSha,
            'repository_url' => $this->repositoryUrl,
        ];
    }

    /**
     * Normalise a raw env value to a non-empty string or null.
     */
    private static function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }
}
