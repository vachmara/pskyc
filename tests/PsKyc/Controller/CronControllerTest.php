<?php

namespace Tests\PsKyc\Controller;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

// Load the ModuleFrontController stub and the controller under test
require_once __DIR__ . '/../Mock/ModuleFrontController.php';
require_once dirname(__DIR__, 3) . '/controllers/front/cron.php';

class CronControllerTest extends MockeryTestCase
{
    /** @var Mockery\MockInterface */
    private $maintenanceServiceMock;

    /** @var Mockery\MockInterface */
    private $moduleMock;

    /** @var Mockery\MockInterface */
    private $moduleStaticMock;

    /** @var Mockery\MockInterface */
    private $toolsMock;

    /** @var Mockery\MockInterface */
    private $loggerMock;

    protected function setUp(): void
    {
        $this->maintenanceServiceMock = \Mockery::mock();
        $this->moduleMock = \Mockery::mock();
        $this->moduleStaticMock = \Mockery::mock();
        $this->toolsMock = \Mockery::mock();
        $this->loggerMock = \Mockery::mock();

        \Module::setStaticExpectations($this->moduleStaticMock);
        \Tools::setStaticExpectations($this->toolsMock);
        \PrestaShopLogger::setStaticExpectations($this->loggerMock);

        $this->loggerMock->shouldReceive('addLog')->byDefault();

        $this->moduleStaticMock->shouldReceive('getInstanceByName')
            ->with('pskyc')
            ->andReturn($this->moduleMock);

        $this->moduleMock->shouldReceive('get')
            ->with('PrestaShop\\Module\\Pskyc\\Service\\MaintenanceService')
            ->andReturn($this->maintenanceServiceMock)
            ->byDefault();
    }

    public function testDailyMaintenanceSuccess()
    {
        $token = 'token123';
        $results = ['success' => true];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('runDailyMaintenance')
            ->once()
            ->andReturn($results);
        $this->maintenanceServiceMock->shouldReceive('logMaintenanceRun')
            ->once()
            ->with($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('daily_maintenance');

        $controller = new \PskycCronModuleFrontController();
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testInvalidToken()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn('wrong');
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('daily_maintenance');

        $controller = new \PskycCronModuleFrontController();
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Bad token', $controller->output);
    }

    public function testServiceInitializationFailure()
    {
        $this->moduleMock->shouldReceive('get')
            ->with('PrestaShop\\Module\\Pskyc\\Service\\MaintenanceService')
            ->once()
            ->andThrow(new \Exception('no service'));

        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Cron service initialization failed: MaintenanceService not available', 3, null, 'Pskyc');

        $controller = new \PskycCronModuleFrontController();
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Service initialization failed', $controller->output);
    }

    public function testUnknownAction()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('invalid');

        $controller = new \PskycCronModuleFrontController();
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Unknown action', $controller->output);
    }
}
