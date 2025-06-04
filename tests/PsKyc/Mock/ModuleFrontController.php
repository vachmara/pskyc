<?php

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
}
