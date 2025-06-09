<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */
class MockProxy
{
    protected static $mock;

    /**
     * Set static expectations
     *
     * @param mixed $mock
     */
    public static function setStaticExpectations($mock)
    {
        static::$mock = $mock;
    }

    /**
     * Any static calls we get are passed along to self::$mock
     *
     * @param string $name
     * @param mixed $args
     *
     * @return mixed
     */
    public static function __callStatic($name, $args)
    {
        return call_user_func_array(
            [static::$mock, $name],
            $args
        );
    }
}

class Context extends MockProxy
{
    protected static $mock;

    /**
     * Get the current context
     *
     * @return Context
     */
    public static function getContext()
    {
        if (!static::$mock) {
            static::$mock = new static();
        }

        return static::$mock;
    }
}

class Db extends MockProxy
{
    protected static $mock;
}

class Configuration extends MockProxy
{
    protected static $mock;
}

class Tools extends MockProxy
{
    protected static $mock;
}

class Module extends MockProxy
{
    protected static $mock;
}

class Validate extends MockProxy
{
    protected static $mock;
}

class Shop extends MockProxy
{
    protected static $mock;
}

class Language extends MockProxy
{
    protected static $mock;

    public static function getIsoById($id)
    {
        return 'en';
    }
}

class Customer extends MockProxy
{
    protected static $mock;
}

class Cart extends MockProxy
{
    protected static $mock;
}

class Order extends MockProxy
{
    protected static $mock;
}

class PrestaShopLogger extends MockProxy
{
    protected static $mock;

    /**
     * Log a message
     *
     * @param string $message
     * @param int $level
     */
    public static function log($message, $level = 1)
    {
        if (static::$mock) {
            static::$mock->log($message, $level);
        } else {
            // Default behavior if no mock is set
            error_log("[$level] $message");
        }
    }
}

class Mail extends MockProxy
{
    protected static $mock;

    /**
     * Store the last processed template content for testing
     */
    public static $lastProcessedContent = [
        'html' => '',
        'txt' => '',
        'templateVars' => [],
        'subject' => '',
        'recipient' => '',
        'template' => '',
    ];

    /**
     * Mock template content for testing
     */
    public static $mockTemplateContent = [
        'html' => 'HTML: Hello {firstname} {lastname}, your verification #{verification_id} is {status_label}',
        'txt' => 'TXT: Hello {firstname} {lastname}, your verification #{verification_id} is {status_label}',
    ];

    /**
     * Enhanced Send method that simulates template processing
     */
    public static function Send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName = null,
        $from = null,
        $fromName = null,
        $fileAttachment = null,
        $mode_smtp = null,
        $templatePath = null,
        $die = false,
        $idShop = null,
        $bcc = null,
        $replyTo = null,
        $replyToName = '',
    ) {
        // Store call details for testing
        static::$lastProcessedContent['template'] = $template;
        static::$lastProcessedContent['subject'] = $subject;
        static::$lastProcessedContent['templateVars'] = $templateVars;
        static::$lastProcessedContent['recipient'] = is_array($to) ? $to[0] : $to;

        // Simulate template loading (use mock content or fall back to simple template)
        $templateHtml = static::$mockTemplateContent['html'] ?? 'Default HTML template with {firstname} {lastname}';
        $templateTxt = static::$mockTemplateContent['txt'] ?? 'Default TXT template with {firstname} {lastname}';

        // Add standard PrestaShop template variables
        $defaultVars = [
            '{shop_name}' => 'Test Shop',
            '{shop_url}' => 'https://example.com/',
            '{my_account_url}' => 'https://example.com/my-account',
            '{color}' => '#DB3484',
        ];

        $allTemplateVars = array_merge($defaultVars, $templateVars);

        // Simulate PrestaShop's template variable replacement using strtr()
        $processedHtml = strtr($templateHtml, $allTemplateVars);
        $processedTxt = strtr($templateTxt, $allTemplateVars);

        // Store processed content for test assertions
        static::$lastProcessedContent['html'] = $processedHtml;
        static::$lastProcessedContent['txt'] = $processedTxt;

        // Call the original mock if it exists, otherwise return success
        if (static::$mock) {
            return static::$mock->Send(
                $idLang, $template, $subject, $templateVars, $to, $toName,
                $from, $fromName, $fileAttachment, $mode_smtp, $templatePath,
                $die, $idShop, $bcc, $replyTo, $replyToName
            );
        }

        return true; // Default success for testing
    }

    /**
     * Resets the mock email state to default values for template content and processed data.
     *
     * Clears the last processed email content and restores the mock template content to its initial state. Intended for use between tests to ensure isolation.
     */
    public static function resetMockState()
    {
        static::$lastProcessedContent = [
            'html' => '',
            'txt' => '',
            'templateVars' => [],
            'subject' => '',
            'recipient' => '',
            'template' => '',
        ];

        static::$mockTemplateContent = [
            'html' => 'HTML: Hello {firstname} {lastname}, your verification #{verification_id} is {status_label}',
            'txt' => 'TXT: Hello {firstname} {lastname}, your verification #{verification_id} is {status_label}',
        ];
    }

    /**
     * Set custom mock template content for specific tests
     */
    public static function setMockTemplateContent($html, $txt = null)
    {
        static::$mockTemplateContent['html'] = $html;
        static::$mockTemplateContent['txt'] = $txt ?? $html;
    }

    /**
     * Get the last processed template content
     */
    public static function getLastProcessedContent()
    {
        return static::$lastProcessedContent;
    }
}

class PrestaShopException extends Exception
{
}
