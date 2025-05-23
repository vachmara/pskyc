<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class PskycUploaderModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $this->context->smarty->assign([
            'module_dir' => _PS_MODULE_DIR_ . 'pskyc/',
            'module_url' => __PS_BASE_URI__ . 'modules/pskyc/',
        ]);

        $this->setTemplate('module:pskyc/views/templates/front/uploader.tpl');
    }
}