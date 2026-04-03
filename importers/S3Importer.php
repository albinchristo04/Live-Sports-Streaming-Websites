<?php
declare(strict_types=1);

require_once __DIR__ . '/ImporterBase.php';

/**
 * S3Importer
 *
 * Imports match data from Server 3's JSON feed.
 *
 * Feed structure:
 *   { "events": {
 *       "streams": [
 *         { "streams": [
 *             { "name", "tag", "category_name", "poster", "viewers",
 *               "starts_at" (Unix ts), "ends_at" (Unix ts), "iframe" }
 *           ] }
 *       ] } }
 *
 * Each inner stream entry represents a single match with one stream.
 * Times are Unix timestamps → converted directly to UTC DateTime.
 */
class S3Importer extends ImporterBase
{
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

        // Navigate: json.events.streams[]
        $outerItems = $data['events']['streams'] ?? [];
        if (!is_array($outerItems)) {
            return [];
        }

        $matches = [];

        foreach ($outerItems as $outerItem) {
            if (!is_array($outerItem)) {
                continue;
            }

            $innerStreams = $outerItem['streams'] ?? [];
            if (!is_array($innerStreams)) {
                continue;
            }

            foreach ($innerStreams as $stream) {
                if (!is_array($stream)) {
                    continue;
                }

                $title = trim((string) ($stream['name'] ?? ''));
                if ($title === '') {
                    continue;
                }

                // ---------- Teams ----------
                $parts    = explode(' vs. ', $title, 2);
                $teamHome = trim($parts[0]);
                $teamAway = isset($parts[1]) ? trim($parts[1]) : null;

                // ---------- Metadata ----------
                $league   = isset($stream['tag'])           ? trim((string) $stream['tag'])           : null;
                $category = isset($stream['category_name']) ? trim((string) $stream['category_name']) : null;
                $poster   = isset($stream['poster'])        ? trim((string) $stream['poster'])        : null;
                $viewers  = isset($stream['viewers'])       ? trim((string) $stream['viewers'])       : null;

                // ---------- Datetime from Unix timestamp ----------
                $startsAt = isset($stream['starts_at']) ? (int) $stream['starts_at'] : 0;

                $matchDatetime = $this->unixToUtcDatetime($startsAt);
                $dateYmd       = (new \DateTime('@' . $startsAt))->format('Y-m-d');

                // ---------- Slug & fingerprint ----------
                $slug        = $this->generateSlug($title, $dateYmd);
                $fingerprint = md5(strtolower($title) . date('Y-m-d', $startsAt));

                // ---------- Single stream ----------
                $iframeUrl = isset($stream['iframe']) ? trim((string) $stream['iframe']) : '';

                $streams = [];
                if ($iframeUrl !== '') {
                    $streams[] = [
                        'channel_name' => 'Server 3',
                        'iframe_url'   => $iframeUrl,
                        'lang'         => null,
                        'sort_order'   => 0,
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
                    'poster_url'      => $poster,
                    'viewers'         => $viewers,
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
     * Convert a Unix timestamp to a UTC DateTime object.
     * Uses PHP's '@timestamp' constructor which is always UTC.
     */
    private function unixToUtcDatetime(int $timestamp): \DateTime
    {
        $dt = new \DateTime('@' . $timestamp);
        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt;
    }
}
