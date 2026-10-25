<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\ViewHelper;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ContentGateViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('category', 'string', 'Consent category identifier required to reveal content', true);
        $this->registerArgument('placeholder', 'string', 'Text shown until consent is given', false, '');
        $this->registerArgument('poster', 'string', 'Placeholder image shown until consent is given', false, '');
        $this->registerArgument('actionLabel', 'string', 'Label of the button that grants this category', false, '');
        $this->registerArgument('media', 'bool', 'Fill the surrounding frame with a media placeholder', false, false);
    }

    public function render(): string
    {
        $category = (string) $this->arguments['category'];
        $placeholder = (string) $this->arguments['placeholder'];
        $poster = (string) $this->arguments['poster'];
        $actionLabel = (string) $this->arguments['actionLabel'];
        $media = (bool) $this->arguments['media'];
        $content = self::deferEmbeddedSources((string) $this->renderChildren());

        $isMedia = $media || $poster !== '';
        $posterHtml = $poster !== ''
            ? '<img class="mai-consent-gate__poster" src="' . htmlspecialchars($poster, ENT_QUOTES) . '" alt="" />'
            : '';
        $actionHtml = $actionLabel !== ''
            ? '<button type="button" class="mai-consent-gate__load" data-consent-action="acceptCategory">'
                . htmlspecialchars($actionLabel, ENT_QUOTES)
                . '</button>'
            : '';
        $playHtml = $isMedia
            ? '<button type="button" class="mai-consent-gate__play" data-consent-action="acceptCategory" aria-label="'
                . htmlspecialchars($actionLabel !== '' ? $actionLabel : $placeholder, ENT_QUOTES)
                . '"></button>'
            : '';
        $mediaClass = $isMedia ? ' mai-consent-gate--media' : '';

        return '<div class="mai-consent-gate' . $mediaClass . '" data-consent-category="'
            . htmlspecialchars($category, ENT_QUOTES) . '" data-consent-state="pending">'
            . '<div class="mai-consent-gate__content" hidden>' . $content . '</div>'
            . '<div class="mai-consent-gate__placeholder">'
            . $posterHtml
            . $playHtml
            . '<p class="mai-consent-gate__text">' . htmlspecialchars($placeholder, ENT_QUOTES) . '</p>'
            . $actionHtml
            . '</div>'
            . '</div>';
    }

    /**
     * Keep third-party frames out of the network until consent copies
     * data-consent-src onto src. Local images inside the gate stay untouched.
     */
    public static function deferEmbeddedSources(string $html): string
    {
        $deferred = preg_replace_callback(
            '/<(iframe|embed|object)\b([^>]*)>/i',
            static function (array $match): string {
                $attrs = $match[2];
                $replaced = preg_replace(
                    '/(?<=\s)src\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                    'data-consent-src=$1',
                    $attrs,
                    1,
                    $count,
                );
                if ($count === 0) {
                    return $match[0];
                }

                return '<' . $match[1] . $replaced . '>';
            },
            $html,
        );

        return is_string($deferred) ? $deferred : $html;
    }
}
