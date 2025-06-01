<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace PrestaShop\Module\Pskyc\Checkout;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class KycStep
 *
 * Simple checkout step that redirects to KYC verification page
 * Only appears when KYC verification is required for cart products
 */
class KycStep extends \AbstractCheckoutStepCore
{
    /**
     * @var string
     */
    private $kycUrl = '';

    public function __construct(\Context $context, TranslatorInterface $translator)
    {
        parent::__construct($context, $translator);

        $this->setTitle($this->getTranslator()->trans('Identity Verification Required', [], 'Modules.Pskyc.Shop'));
        $this->setTemplate('module:pskyc/views/templates/front/checkout/kyc-step.tpl');
    }

    /**
     * Set KYC URL
     *
     * @param string $url
     *
     * @return $this
     */
    public function setKycUrl($url)
    {
        $this->kycUrl = $url;

        return $this;
    }

    /**
     * Get KYC URL
     *
     * @return string
     */
    public function getKycUrl()
    {
        return $this->kycUrl;
    }

    /**
     * Handle step request - this step doesn't process forms
     *
     * @param array $requestParams
     *
     * @return $this
     */
    public function handleRequest(array $requestParams = [])
    {
        // This step is informational only, no form processing needed
        return $this;
    }

    /**
     * Render the step
     *
     * @param array $extraParams
     *
     * @return string
     */
    public function render(array $extraParams = [])
    {
        $templateParams = [
            'kyc_url' => $this->getKycUrl(),
        ];

        return $this->renderTemplate(
            $this->getTemplate(),
            $templateParams,
            $extraParams
        );
    }

    /**
     * Get step identifier
     *
     * @return string
     */
    public function getIdentifier()
    {
        return 'kyc-verification';
    }
}
