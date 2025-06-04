<?php

namespace Tests\PsKyc\Controller;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\CustomerRepository;
use PrestaShop\Module\Pskyc\Service\VerificationService;

class VerifyFrontControllerTest extends MockeryTestCase
{
    /** @var TestVerifyController */
    private $controller;

    protected function setUp(): void
    {
        $context = new \stdClass();
        $context->customer = (object) ['id' => 1, 'secure_key' => 'key'];
        $context->smarty = new class {
            public $tpl_vars;

            public function assign($params)
            {
            }
        };
        $context->link = \Mockery::mock();
        \Context::setStaticExpectations($context);

        $this->controller = new class extends \PskycVerifyModuleFrontController {
            public $errors = [];

            public function callValidateUploadedFile($file)
            {
                return $this->validateUploadedFile($file);
            }

            protected function setTemplate($template)
            {
                $this->template = $template;
            }

            public function trans($string, array $params = [], $domain = null)
            {
                return $string;
            }
        };
    }

    private function createPngFile(): string
    {
        $data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3eAAAAAASUVORK5CYII=');
        $file = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($file, $data);

        return $file;
    }

    public function testValidateUploadedFileSuccess(): void
    {
        $path = $this->createPngFile();
        $file = ['error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'tmp_name' => $path];

        $this->assertTrue($this->controller->callValidateUploadedFile($file));
    }

    public function testValidateUploadedFileTooLarge(): void
    {
        $path = $this->createPngFile();
        $file = ['error' => UPLOAD_ERR_OK, 'size' => 20 * 1024 * 1024, 'tmp_name' => $path];

        $this->assertFalse($this->controller->callValidateUploadedFile($file));
    }

    public function testValidateUploadedFileInvalidMime(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($tmp, 'dummy');
        $file = ['error' => UPLOAD_ERR_OK, 'size' => filesize($tmp), 'tmp_name' => $tmp];

        $this->assertFalse($this->controller->callValidateUploadedFile($file));
    }

    public function testGetCustomerDataSuccess(): void
    {
        $module = \Mockery::mock();
        $repo = \Mockery::mock(CustomerRepository::class);
        $repo->shouldReceive('getCustomerData')->with(1)->andReturn(['id' => 1]);
        $module->shouldReceive('get')->with('PrestaShop\\Module\\Pskyc\\Repository\\CustomerRepository')->andReturn($repo);
        $this->controller->module = $module;

        $ref = new \ReflectionClass($this->controller);
        $method = $ref->getMethod('getCustomerData');
        $method->setAccessible(true);
        $result = $method->invoke($this->controller, 1);

        $this->assertSame(['id' => 1], $result);
    }

    public function testGetCustomerDataException(): void
    {
        $module = \Mockery::mock();
        $repo = \Mockery::mock(CustomerRepository::class);
        $repo->shouldReceive('getCustomerData')->andThrow(new \Exception('fail'));
        $module->shouldReceive('get')->with('PrestaShop\\Module\\Pskyc\\Repository\\CustomerRepository')->andReturn($repo);
        \PrestaShopLogger::setStaticExpectations(\Mockery::mock()->shouldReceive('addLog')->once()->getMock());
        $this->controller->module = $module;

        $ref = new \ReflectionClass($this->controller);
        $method = $ref->getMethod('getCustomerData');
        $method->setAccessible(true);
        $result = $method->invoke($this->controller, 1);

        $this->assertNull($result);
    }

    public function testGetCustomerVerification(): void
    {
        $service = \Mockery::mock(VerificationService::class);
        $service->shouldReceive('getMostRecentVerification')->with(1)->andReturn(['id_kyc_verification' => 2]);
        $prop = new \ReflectionProperty(\PskycVerifyModuleFrontController::class, 'verificationService');
        $prop->setAccessible(true);
        $prop->setValue($this->controller, $service);

        $ref = new \ReflectionMethod($this->controller, 'getCustomerVerification');
        $ref->setAccessible(true);
        $result = $ref->invoke($this->controller);

        $this->assertSame(['id_kyc_verification' => 2], $result);
    }

    public function testGetBreadcrumbLinks(): void
    {
        $link = \Mockery::mock();
        $link->shouldReceive('getModuleLink')->with('pskyc', 'verify', [], true)->andReturn('link');
        $context = \Context::getContext();
        $context->link = $link;
        $this->controller->module = (object) ['name' => 'pskyc'];

        $result = $this->controller->getBreadcrumbLinks();

        $this->assertSame('link', $result['links'][1]['url']);
    }
}
