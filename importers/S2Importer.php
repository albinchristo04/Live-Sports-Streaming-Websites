<?php
declare(strict_types=1);

require_once __DIR__ . '/ImporterBase.php';

/**
 * S2Importer
 *
 * Imports match data from Server 2's JSON feed.
 *
 * Feed structure:
 *   { "last_updated": "YYYY-MM-DD HH:MM:SS",
 *     "events": {
 *       "MONDAY": [ { "event", "time" (HH:MM UTC), "streams": ["url1","url2"] } ],
 *       "TUESDAY": [ … ],
 *       … } }
 *
 * Times are already in UTC. Day names are resolved to actual calendar dates
 * relative to the `last_updated` timestamp.
 */
class S2Importer extends ImporterBase
{
    /** @var string[] English day-name → PHP date() 'N' number (1=Mon … 7=Sun) */
    private const DAY_NUMBER = [
        'MONDAY'    => 1,
        'TUESDAY'   => 2,
        'WEDNESDAY' => 3,
        'THURSDAY'  => 4,
        'FRIDAY'    => 5,
        'SATURDAY'  => 6,
        'SUNDAY'    => 7,
    ];

    // -------------------------------------------------------------------------
    // Abstract implementations
    // -------------------------------------------------------------------------

    protected function fetchAndParse(): array
    {
        return $this->fetchJson();
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function parseMatches(): array
    {
        $data = $this->fetchAndParse();

        $lastUpdatedRaw = trim((string) ($data['last_updated'] ?? ''));
        $baseDate       = $this->parseLastUpdated($lastUpdatedRaw);

        $eventsByDay = $data['events'] ?? [];
        if (!is_array($eventsByDay)) {
            return [];
        }

        $matches = [];

        foreach ($eventsByDay as $dayName => $dayEvents) {
            if (!is_array($dayEvents)) {
                continue;
            }

            $dayKey  = strtoupper(trim((string) $dayName));
            $dateYmd = $this->resolveDate($dayKey, $baseDate);

            foreach ($dayEvents as $event) {
                if (!is_array($event)) {
                    continue;
                }

                $rawTitle = trim((string) ($event['event'] ?? ''));
                if ($rawTitle === '') {
                    continue;
                }

                // ---------- League extraction & title cleanup ----------
                ['title' => $title, 'league' => $league] = $this->extractLeague($rawTitle);

                // ---------- Teams ----------
                $parts    = explode(' x ', $title, 2);
                $teamHome = trim($parts[0]);
                $teamAway = isset($parts[1]) ? trim($parts[1]) : null;

                // ---------- Datetime (UTC) ----------
                $rawTime       = trim((string) ($event['time'] ?? '00:00'));
                $matchDatetime = $this->buildUtcDatetime($dateYmd, $rawTime);

                // ---------- Slug & fingerprint ----------
                $slug        = $this->generateSlug($title, $dateYmd);
                $fingerprint = md5(strtolower($title) . $dateYmd);

                // ---------- Streams ----------
                $rawStreams = $event['streams'] ?? [];
                if (!is_array($rawStreams)) {
                    $rawStreams = [];
                }

                $streams = [];
                foreach ($rawStreams as $idx => $streamUrl) {
                    $streamUrl = trim((string) $streamUrl);
                    if ($streamUrl === '') {
                        continue;
                    }

                    $streams[] = [
                        'channel_name' => $this->labelFromUrl($streamUrl, $idx),
                        'iframe_url'   => $streamUrl,
                        'lang'         => null,
                        'sort_order'   => $idx,
                    ];
                }

                $matches[] = [
                    'title'           => $title,
                    'slug'            => $slug,
                    'league'          => $league,
                    'category'        => null,
                    'team_home'       => $teamHome,
                    'team_away'       => $teamAway,
                    'match_datetime'  => $matchDatetime,
                    'display_datetime'=> null,
                    'poster_url'      => null,
                    'viewers'         => null,
                    'fingerprint'     => $fingerprint,
                    'streams'         => $streams,
                ];
            }
        }

        return $matches;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parse the `last_updated` field into a UTC DateTime.
     * Falls back to "now" on any parse failure.
     */
    private function parseLastUpdated(string $raw): \DateTime
    {
        if ($raw === '') {
            return new \DateTime('now', new \DateTimeZone('UTC'));
        }

        // Try ISO 8601 with microseconds first (live feed format: 2026-04-03T01:23:47.105502Z)
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $raw, new \DateTimeZone('UTC'));
        if ($dt !== false) {
            return $dt;
        }

        // Try ISO 8601 without microseconds
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $raw, new \DateTimeZone('UTC'));
        if ($dt !== false) {
            return $dt;
        }

        // Try simple datetime format
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw, new \DateTimeZone('UTC'));
        if ($dt !== false) {
            return $dt;
        }

        // Last resort: let PHP try to parse it
        try {
            return new \DateTime($raw, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return new \DateTime('now', new \DateTimeZone('UTC'));
        }
    }

    /**
     * Given a day name like "THURSDAY" and a base DateTime, return the
     * YYYY-MM-DD string for that day within the same ISO week as the base date.
     * If the day has already passed this week, we step to next week.
     */
    private function resolveDate(string $dayName, \DateTime $base): string
    {
        $targetDayNum = self::DAY_NUMBER[$dayName] ?? null;

        if ($targetDayNum === null) {
            // Unknown day name — return the base date itself
            return $base->format('Y-m-d');
        }

        // Clone base so we don't mutate it
        $candidate = clone $base;

        // Current ISO day number (1=Mon … 7=Sun)
        $currentDayNum = (int) $candidate->format('N');

        // Compute delta to reach the target day within the current ISO week
        $delta = $targetDayNum - $currentDayNum;

        // Always use the upcoming occurrence (including today)
        if ($delta < 0) {
            $delta += 7;
        }

        if ($delta > 0) {
            $candidate->modify("+{$delta} days");
        }

        return $candidate->format('Y-m-d');
    }

    /**
     * Combine a YYYY-MM-DD date string and an HH:MM time string (UTC)
     * into a UTC DateTime object.
     */
    private function buildUtcDatetime(string $dateYmd, string $time): \DateTime
    {
        $utc = new \DateTimeZone('UTC');
        $dt  = \DateTime::createFromFormat('Y-m-d H:i', $dateYmd . ' ' . $time, $utc);

        if ($dt === false) {
            $dt = new \DateTime($dateYmd . ' 00:00:00', $utc);
        }

        return $dt;
    }

    /**
     * Extract a league name from the event title, returning both the
     * cleaned title and the extracted league (or null).
     *
     * Handled patterns:
     *   - "NBA: Team A x Team B"   → league = "NBA", title stripped of prefix
     *   - "UFC …"                  → league = "UFC"
     *   - "Tennis - Player A x …"  → league = "Tennis" (known sport prefix)
     *   - Otherwise                → league = null, title unchanged
     *
     * @return array{title: string, league: string|null}
     */
    private function extractLeague(string $rawTitle): array
    {
        // Pattern 1: "LEAGUE: rest" (colon prefix)
        if (preg_match('/^([A-Za-z0-9 ]+):\s+(.+)$/', $rawTitle, $m)) {
            return ['title' => trim($m[2]), 'league' => trim($m[1])];
        }

        // Pattern 2: starts with "UFC"
        if (stripos($rawTitle, 'UFC') === 0) {
            return ['title' => $rawTitle, 'league' => 'UFC'];
        }

        // Pattern 3: "LeagueName - rest" where LeagueName is a recognised sport keyword
        $sportKeywords = [
            'Tennis', 'Boxing', 'Cricket', 'Rugby', 'Golf',
            'Cycling', 'Motorsport', 'Formula', 'Athletics',
            'Swimming', 'Volleyball', 'Basketball', 'Baseball',
            'Hockey', 'Handball', 'Snooker', 'Darts',
        ];

        if (preg_match('/^(.+?) - (.+)$/', $rawTitle, $m)) {
            $possibleLeague = trim($m[1]);
            foreach ($sportKeywords as $keyword) {
                if (stripos($possibleLeague, $keyword) !== false) {
                    return ['title' => trim($m[2]), 'league' => $possibleLeague];
                }
            }
        }

        return ['title' => $rawTitle, 'league' => null];
    }

    /**
     * Derive a human-readable stream label from a URL.
     * Uses the last meaningful path segment, strips extensions,
     * and falls back to "Stream N" (1-indexed).
     */
    private function labelFromUrl(string $url, int $index): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if ($path !== null && $path !== '' && $path !== '/') {
            // Strip trailing slash, then grab last segment
            $segment = basename(rtrim($path, '/'));
            // Remove common extensions
            $segment = preg_replace('/\.(php|html?|m3u8|mp4|ts)$/i', '', $segment) ?? $segment;
            $segment = trim($segment, '-_');

            if ($segment !== '' && $segment !== '.') {
                return $segment;
            }
        }

        return 'Stream ' . ($index + 1);
    }
}
