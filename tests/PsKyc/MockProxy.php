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
}

class Mail extends MockProxy
{
    protected static $mock;
}

class PrestaShopException extends Exception
{
}
