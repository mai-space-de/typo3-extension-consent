<?php

declare(strict_types = 1);

namespace Maispace\MaiConsent\Service;

use Psr\Http\Message\ServerRequestInterface;

class ConsentCookieService
{
    private const COOKIE_NAME = 'mai_consent';

    /**
     * Parses the consent cookie from the request and returns a preference map.
     *
     * @return array<int|string, bool>
     */
    public function getPreferences(ServerRequestInterface $request): array
    {
        $cookies = $request->getCookieParams();
        $cookieValue = $cookies[self::COOKIE_NAME] ?? '';

        if (!is_string($cookieValue) || $cookieValue === '') {
            return [];
        }

        return $this->parseCookieValue($cookieValue);
    }

    /**
     * Returns true if the given category UID has a positive consent preference.
     *
     * @param array<int|string, bool> $preferences
     */
    public function hasConsent(array $preferences, int $categoryUid): bool
    {
        return isset($preferences[$categoryUid]) && $preferences[$categoryUid] === true;
    }

    /**
     * Returns true only if every category UID in $categoryUids has a decision (true or false) in $preferences.
     *
     * @param array<int|string, bool> $preferences
     * @param int[]                   $categoryUids
     */
    public function areAllDecided(array $preferences, array $categoryUids): bool
    {
        foreach ($categoryUids as $uid) {
            if (!array_key_exists($uid, $preferences) && !array_key_exists((string)$uid, $preferences)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Encodes preferences to a JSON string suitable for storing in a cookie.
     *
     * @param array<int|string, bool> $preferences
     */
    public function buildCookieValue(array $preferences): string
    {
        $encoded = json_encode($preferences, JSON_FORCE_OBJECT);

        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * Decodes a cookie value string into a preferences array.
     * Returns an empty array on any parse error.
     *
     * @return array<int|string, bool>
     */
    public function parseCookieValue(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $key => $val) {
            if (!is_bool($val)) {
                continue;
            }

            $result[$key] = $val;
        }

        return $result;
    }
}
