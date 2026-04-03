<?php

/**
 * getGPTSlotDefinitions(string $pageType): array
 * Returns an array of GPT slot config arrays for 'homepage' or 'match' page types.
 *
 * Each slot: ['div_id' => string, 'path' => string, 'sizes' => array]
 */
function getGPTSlotDefinitions(string $pageType): array {
    $networkId = defined('GPT_NETWORK_ID') ? GPT_NETWORK_ID : '23250651813';

    $slots = [
        'homepage' => [
            [
                'div_id' => 'div-gpt-hp-header',
                'path'   => '/' . $networkId . '/hp-header',
                'sizes'  => [[728, 90], [970, 90], [320, 50]],
            ],
            [
                'div_id' => 'div-gpt-hp-infeed',
                'path'   => '/' . $networkId . '/hp-infeed',
                'sizes'  => [[300, 250], [336, 280]],
            ],
            [
                'div_id' => 'div-gpt-hp-footer',
                'path'   => '/' . $networkId . '/hp-footer',
                'sizes'  => [[728, 90], [320, 50]],
            ],
        ],
        'match' => [
            [
                'div_id' => 'div-gpt-match-header',
                'path'   => '/' . $networkId . '/match-header',
                'sizes'  => [[728, 90], [970, 90], [320, 50]],
            ],
            [
                'div_id' => 'div-gpt-match-above',
                'path'   => '/' . $networkId . '/match-above-player',
                'sizes'  => [[728, 90], [320, 50]],
            ],
            [
                'div_id' => 'div-gpt-match-below',
                'path'   => '/' . $networkId . '/match-below-player',
                'sizes'  => [[300, 250], [336, 280], [728, 90]],
            ],
            [
                'div_id' => 'div-gpt-match-sidebar',
                'path'   => '/' . $networkId . '/match-sidebar',
                'sizes'  => [[300, 250], [300, 600], [160, 600]],
            ],
            [
                'div_id' => 'div-gpt-match-footer',
                'path'   => '/' . $networkId . '/match-footer',
                'sizes'  => [[728, 90], [320, 50]],
            ],
        ],
    ];

    return $slots[$pageType] ?? [];
}

/**
 * renderAdSlot(string $divId, string $adUnitPath, array $sizes): string
 * Returns the HTML markup for a GPT ad container div.
 */
function renderAdSlot(string $divId, string $adUnitPath, array $sizes): string {
    $escapedId   = htmlspecialchars($divId, ENT_QUOTES, 'UTF-8');
    $escapedPath = htmlspecialchars($adUnitPath, ENT_QUOTES, 'UTF-8');

    // Build a readable size label for accessibility / debugging
    $sizeLabels = implode(', ', array_map(fn($s) => $s[0] . 'x' . $s[1], $sizes));

    return '<div id="' . $escapedId . '" class="ad-slot" '
         . 'data-ad-unit="' . $escapedPath . '" '
         . 'data-sizes="' . htmlspecialchars($sizeLabels, ENT_QUOTES, 'UTF-8') . '" '
         . 'aria-label="Advertisement">'
         . '</div>' . PHP_EOL;
}

/**
 * getAdsterraScript(): string
 * Returns the Adsterra popup/push script tag.
 */
function getAdsterraScript(): string {
    return '<script src="https://widthwidowzoology.com/22/9f/cc/229fcc7fac3be2c689fa4fa174ce4169.js"></script>' . PHP_EOL;
}
