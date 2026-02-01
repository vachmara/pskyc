<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */
class ModuleFrontController
{
    public $ajax = false;
    public $module;
    public $output = '';

    public function __construct()
    {
    }

    public function ajaxRender(string $content)
    {
        $this->output = $content;
    }

    public function initContent()
    {
        // Empty implementation for testing
    }

    public function setTemplate($template)
    {
        // Empty implementation for testing
    }

    public function getBreadcrumbLinks()
    {
        return ['links' => []];
    }

    public function addMyAccountToBreadcrumb()
    {
        return ['title' => 'My Account', 'url' => '/my-account'];
    }

    public function trans($message, $parameters = [], $domain = null)
    {
        return $message;
    }
}
