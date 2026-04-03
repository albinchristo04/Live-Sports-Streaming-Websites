<?php
declare(strict_types=1);

/**
 * ImporterBase
 *
 * Abstract base class for all JSON feed importers.
 * Provides shared slug/fingerprint helpers and the saveMatch() persistence logic.
 */
abstract class ImporterBase
{
    protected PDO    $pdo;
    protected string $jsonUrl;

    public function __construct(PDO $pdo, string $jsonUrl)
    {
        $this->pdo     = $pdo;
        $this->jsonUrl = $jsonUrl;
    }

    // -------------------------------------------------------------------------
    // Abstract interface
    // -------------------------------------------------------------------------

    /**
     * Fetch the remote JSON feed and return a decoded PHP array.
     * Throws RuntimeException on network or parse failure.
     *
     * @return array<mixed>
     */
    abstract protected function fetchAndParse(): array;

    /**
     * Parse the raw feed array into a normalised list of match data arrays.
     *
     * Each element must contain:
     *   title, slug, league, category, team_home, team_away,
     *   match_datetime (UTC DateTime), display_datetime (DateTime|null),
     *   poster_url, viewers, fingerprint,
     *   streams: [ [channel_name, iframe_url, lang, sort_order], … ]
     *
     * @return array<array<string,mixed>>
     */
    abstract public function parseMatches(): array;

    // -------------------------------------------------------------------------
    // Persistence
    // -------------------------------------------------------------------------

    /**
     * Persist one match (and its streams) to the database.
     *
     * - If a non-deleted row with the same fingerprint already exists → skip (false).
     * - If a soft-deleted row with the same fingerprint exists → restore it (true).
     * - Otherwise insert a new row into `matches` and all rows into `match_streams`.
     *
     * @param array<string,mixed> $matchData
     * @param int                 $serverId
     * @return bool true when a row was inserted or restored, false when skipped.
     */
    public function saveMatch(array $matchData, int $serverId): bool
    {
        $fingerprint = $matchData['fingerprint'];

        // Check for an existing row (active OR soft-deleted)
        $stmt = $this->pdo->prepare(
            'SELECT id, deleted_at FROM matches WHERE fingerprint = ? LIMIT 1'
        );
        $stmt->execute([$fingerprint]);
        $existing = $stmt->fetch();

        if ($existing !== false) {
            if ($existing['deleted_at'] === null) {
                // Active duplicate — skip
                return false;
            }

            // Soft-deleted — restore
            $upd = $this->pdo->prepare(
                'UPDATE matches SET deleted_at = NULL, updated_at = NOW() WHERE id = ?'
            );
            $upd->execute([$existing['id']]);
            return true;
        }

        // ---- Insert new match -----------------------------------------------
        $matchDatetime   = $matchData['match_datetime'] instanceof \DateTimeInterface
            ? $matchData['match_datetime']->format('Y-m-d H:i:s')
            : (string) $matchData['match_datetime'];

        $displayDatetime = isset($matchData['display_datetime']) && $matchData['display_datetime'] instanceof \DateTimeInterface
            ? $matchData['display_datetime']->format('Y-m-d H:i:s')
            : ($matchData['display_datetime'] ?? null);

        $slug = $this->ensureUniqueSlug((string) $matchData['slug']);

        $ins = $this->pdo->prepare(
            'INSERT INTO matches
                (title, slug, league, category, team_home, team_away,
                 match_datetime, display_datetime, poster_url, viewers,
                 server_id, fingerprint, deleted_at)
             VALUES
                (:title, :slug, :league, :category, :team_home, :team_away,
                 :match_datetime, :display_datetime, :poster_url, :viewers,
                 :server_id, :fingerprint, NULL)'
        );

        $ins->execute([
            ':title'           => $matchData['title'],
            ':slug'            => $slug,
            ':league'          => $matchData['league']          ?? null,
            ':category'        => $matchData['category']        ?? null,
            ':team_home'       => $matchData['team_home']       ?? null,
            ':team_away'       => $matchData['team_away']       ?? null,
            ':match_datetime'  => $matchDatetime,
            ':display_datetime'=> $displayDatetime,
            ':poster_url'      => $matchData['poster_url']      ?? null,
            ':viewers'         => $matchData['viewers']         ?? null,
            ':server_id'       => $serverId,
            ':fingerprint'     => $fingerprint,
        ]);

        $matchId = (int) $this->pdo->lastInsertId();

        // ---- Insert streams -------------------------------------------------
        $streamIns = $this->pdo->prepare(
            'INSERT INTO match_streams
                (match_id, channel_name, iframe_url, lang, sort_order)
             VALUES
                (:match_id, :channel_name, :iframe_url, :lang, :sort_order)'
        );

        foreach ($matchData['streams'] as $stream) {
            $streamIns->execute([
                ':match_id'    => $matchId,
                ':channel_name'=> $stream['channel_name'] ?? null,
                ':iframe_url'  => $stream['iframe_url'],
                ':lang'        => $stream['lang']         ?? null,
                ':sort_order'  => $stream['sort_order']   ?? 0,
            ]);
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Slug helpers
    // -------------------------------------------------------------------------

    /**
     * Convert arbitrary text to a URL-safe hyphenated slug.
     */
    protected function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Replace accented/special chars with ASCII equivalents where possible
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        // Replace any non-alphanumeric character with a hyphen
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        // Trim leading/trailing hyphens
        return trim($text, '-');
    }

    /**
     * Build a slug from a match title + date suffix (YYYY-MM-DD).
     * Does NOT guarantee uniqueness against the database; use ensureUniqueSlug() for that.
     */
    protected function generateSlug(string $title, string $date): string
    {
        $base = $this->slugify($title);
        $datePart = $this->slugify($date); // already YYYY-MM-DD, stays clean
        return $base . '-' . $datePart;
    }

    /**
     * Guarantee a slug is unique in `matches.slug`.
     * Appends -2, -3, … until an unused value is found.
     */
    protected function ensureUniqueSlug(string $slug): string
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM matches WHERE slug = ?');

        $candidate = $slug;
        $counter   = 1;

        while (true) {
            $stmt->execute([$candidate]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidate;
            }
            $counter++;
            $candidate = $slug . '-' . $counter;
        }
    }

    // -------------------------------------------------------------------------
    // Fingerprint helper
    // -------------------------------------------------------------------------

    /**
     * Return true if an active (non-deleted) match with this fingerprint exists.
     */
    protected function fingerprintExists(string $fingerprint): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM matches WHERE fingerprint = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$fingerprint]);
        return (int) $stmt->fetchColumn() > 0;
    }

    // -------------------------------------------------------------------------
    // HTTP / JSON fetch
    // -------------------------------------------------------------------------

    /**
     * Fetch the configured JSON URL and return the decoded array.
     *
     * @return array<mixed>
     * @throws \RuntimeException on network or JSON decode failure.
     */
    protected function fetchJson(): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout'     => 15,
                'user_agent'  => 'Mozilla/5.0 (compatible; SportsFetcher/1.0)',
                'method'      => 'GET',
                'ignore_errors' => false,
            ],
        ]);

        $raw = @file_get_contents($this->jsonUrl, false, $context);

        if ($raw === false) {
            throw new \RuntimeException(
                'Failed to fetch JSON from: ' . $this->jsonUrl
            );
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'Invalid JSON response from: ' . $this->jsonUrl
                . ' (error: ' . json_last_error_msg() . ')'
            );
        }

        return $decoded;
    }
}
