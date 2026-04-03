<?php
declare(strict_types=1);

require_once __DIR__ . '/ImporterBase.php';

/**
 * S1Importer
 *
 * Imports match data from Server 1's JSON feed.
 *
 * Feed structure:
 *   { "events": [ { "teams", "league", "date" (DD-MM-YYYY), "time" (HH:MM CET),
 *                   "channels": [ {"id", "lang"} ] } ],
 *     "player_streams": [...] }
 *
 * Times are in CET (Europe/Paris). Dates are in DD-MM-YYYY format.
 * Stream URLs follow the pattern: https://cartelive.club/player/{channelId}/1
 *
 * League → category mapping covers major North-American sports leagues plus
 * common European football competitions.
 */
class S1Importer extends ImporterBase
{
    // -------------------------------------------------------------------------
    // League → category map
    // -------------------------------------------------------------------------

    /** @var array<string,string> */
    private const LEAGUE_CATEGORY_MAP = [
        'MLB'  => 'Baseball',
        'NBA'  => 'Basketball',
        'NHL'  => 'Ice Hockey',
        'NFL'  => 'American Football',
        'MLS'  => 'Football',
        'UFC'  => 'MMA',
        'PGA'  => 'Golf',
        'F1'   => 'Motorsport',
        'FORMULA 1' => 'Motorsport',
        'TENNIS'    => 'Tennis',
        'BOXING'    => 'Boxing',
        'RUGBY'     => 'Rugby',
        'CYCLING'   => 'Cycling',
        'PREMIER LEAGUE'     => 'Football',
        'LA LIGA'            => 'Football',
        'BUNDESLIGA'         => 'Football',
        'SERIE A'            => 'Football',
        'LIGUE 1'            => 'Football',
        'CHAMPIONS LEAGUE'   => 'Football',
        'EUROPA LEAGUE'      => 'Football',
        'WORLD CUP'          => 'Football',
        'COPA AMERICA'       => 'Football',
        'EURO'               => 'Football',
        'EREDIVISIE'         => 'Football',
        'PRIMEIRA LIGA'      => 'Football',
        'SUPER LIG'          => 'Football',
        'BRASILEIRAO'        => 'Football',
        'LIGA MX'            => 'Football',
        'EREDIVISIE'         => 'Football',
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
        $data   = $this->fetchAndParse();
        $events = $data['events'] ?? [];

        if (!is_array($events)) {
            return [];
        }

        $matches = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $teams = trim((string) ($event['teams'] ?? ''));
            if ($teams === '') {
                continue;
            }

            // ---------- Split teams ----------
            $parts    = explode(' - ', $teams, 2);
            $teamHome = trim($parts[0]);
            $teamAway = isset($parts[1]) ? trim($parts[1]) : null;

            // ---------- League & category ----------
            $league   = isset($event['league']) ? trim((string) $event['league']) : null;
            $category = $this->guessCategory($league);

            // ---------- Date: DD-MM-YYYY → YYYY-MM-DD ----------
            $rawDate = trim((string) ($event['date'] ?? ''));
            $dateYmd = $this->parseDdMmYyyy($rawDate);

            // ---------- Time: HH:MM in CET → UTC DateTime ----------
            $rawTime        = trim((string) ($event['time'] ?? '00:00'));
            $matchDatetime  = $this->cetToUtc($dateYmd, $rawTime);

            // ---------- Title & slug ----------
            $title       = $teams;
            $slug        = $this->generateSlug($title, $dateYmd);
            $fingerprint = md5(strtolower($title) . $dateYmd);

            // ---------- Streams ----------
            $channels = $event['channels'] ?? [];
            if (!is_array($channels)) {
                $channels = [];
            }

            $streams = [];
            foreach ($channels as $idx => $channel) {
                if (!is_array($channel)) {
                    continue;
                }
                $channelId   = trim((string) ($channel['id'] ?? ''));
                $lang        = isset($channel['lang']) ? strtolower(trim((string) $channel['lang'])) : null;
                $iframeUrl   = 'https://cartelive.club/player/' . $channelId . '/1';
                $channelName = $lang !== null ? ('HD ' . strtoupper($lang)) : 'HD';

                $streams[] = [
                    'channel_name' => $channelName,
                    'iframe_url'   => $iframeUrl,
                    'lang'         => $lang,
                    'sort_order'   => $idx,
                ];
            }

            $matches[] = [
                'title'           => $title,
                'slug'            => $slug,
                'league'          => $league,
                'category'        => $category,
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

        return $matches;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Convert a DD-MM-YYYY string to YYYY-MM-DD.
     * Falls back to today's date on parse failure.
     */
    private function parseDdMmYyyy(string $raw): string
    {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
        // Graceful fallback
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d');
    }

    /**
     * Combine a YYYY-MM-DD date and an HH:MM time in Europe/Paris (CET/CEST)
     * and return the equivalent UTC DateTime.
     */
    private function cetToUtc(string $dateYmd, string $time): \DateTime
    {
        $tz = new \DateTimeZone('Europe/Paris');
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $dateYmd . ' ' . $time, $tz);

        if ($dt === false) {
            // Fallback to midnight UTC
            $dt = new \DateTime($dateYmd . ' 00:00:00', new \DateTimeZone('UTC'));
            return $dt;
        }

        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt;
    }

    /**
     * Guess a human-readable sport category from the league name.
     */
    private function guessCategory(?string $league): string
    {
        if ($league === null || $league === '') {
            return 'Football';
        }

        $key = strtoupper(trim($league));

        // Exact match first
        if (isset(self::LEAGUE_CATEGORY_MAP[$key])) {
            return self::LEAGUE_CATEGORY_MAP[$key];
        }

        // Partial / contains match
        foreach (self::LEAGUE_CATEGORY_MAP as $needle => $cat) {
            if (str_contains($key, $needle)) {
                return $cat;
            }
        }

        // Default
        return 'Football';
    }
}
