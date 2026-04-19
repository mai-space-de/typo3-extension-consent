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
    }

    public function render(): string
    {
        $category = $this->arguments['category'];
        $placeholder = $this->arguments['placeholder'];
        $content = $this->renderChildren();

        return sprintf(
            '<div class="mai-consent-gate" data-consent-category="%s" data-consent-state="pending">'
            . '<div class="mai-consent-gate__content" hidden>%s</div>'
            . '<div class="mai-consent-gate__placeholder">%s</div>'
            . '</div>',
            htmlspecialchars($category, ENT_QUOTES),
            $content,
            htmlspecialchars($placeholder, ENT_QUOTES)
        );
    }
}
