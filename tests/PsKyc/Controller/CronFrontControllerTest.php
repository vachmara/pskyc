<?php

namespace Tests\PsKyc\Controller;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Service\MaintenanceService;

class CronFrontControllerTest extends MockeryTestCase
{
    /** @var MaintenanceService */
    private $maintenanceService;

    /** @var PskycCronModuleFrontController */
    private $controller;

    protected function setUp(): void
    {
        $this->maintenanceService = \Mockery::mock(MaintenanceService::class);

        $moduleMock = \Mockery::mock();
        $moduleMock->shouldReceive('get')
            ->with('PrestaShop\\Module\\Pskyc\\Service\\MaintenanceService')
            ->andReturn($this->maintenanceService);

        $toolsMock = \Mockery::mock();
        \Tools::setStaticExpectations($toolsMock);

        \PrestaShopLogger::setStaticExpectations(\Mockery::mock()->shouldIgnoreMissing());

        $this->controller = new class extends \PskycCronModuleFrontController {
            public $output;

            protected function ajaxRender($content)
            {
                $this->output = $content;
            }
        };
        $this->controller->module = $moduleMock;
        $prop = new \ReflectionProperty(\PskycCronModuleFrontController::class, 'maintenanceService');
        $prop->setAccessible(true);
        $prop->setValue($this->controller, $this->maintenanceService);
    }

    public function testDailyMaintenanceAction(): void
    {
        $this->maintenanceService->shouldReceive('getCronToken')->once()->andReturn('secret');
        $this->maintenanceService->shouldReceive('runDailyMaintenance')->once()->andReturn(['done' => true]);
        $this->maintenanceService->shouldReceive('logMaintenanceRun')->once()->with(['done' => true]);

        \Tools::shouldReceive('getValue')->with('token')->andReturn('secret');
        \Tools::shouldReceive('getValue')->with('action', 'daily_maintenance')->andReturn('daily_maintenance');

        ob_start();
        $this->controller->postProcess();
        ob_end_clean();

        $this->assertEquals(json_encode(['done' => true]), $this->controller->output);
    }

    public function testRunMethods(): void
    {
        \Tools::shouldReceive('getValue')->with('warning_days', 30)->andReturn(10)->byDefault();
        \Tools::shouldReceive('getValue')->with('retention_days', 0)->andReturn(0)->byDefault();
        \Tools::shouldReceive('getValue')->with('max_age_hours', 24)->andReturn(12)->byDefault();

        $this->maintenanceService->shouldReceive('cleanupExpiredDocuments')->once()->andReturn(['ok' => true]);
        $this->maintenanceService->shouldReceive('cleanupOldLogs')->once()->with(null)->andReturn(['ok' => true]);
        $this->maintenanceService->shouldReceive('cleanupTempFiles')->once()->with(12)->andReturn(['ok' => true]);
        $this->maintenanceService->shouldReceive('sendExpiryWarnings')->once()->with(10)->andReturn(['ok' => true]);
        $this->maintenanceService->shouldReceive('updateExpiredVerifications')->once()->andReturn(['ok' => true]);

        $reflection = new \ReflectionClass($this->controller);
        foreach ([
            'runDocumentCleanup',
            'runLogCleanup',
            'runTempCleanup',
            'runExpiryWarnings',
            'runUpdateExpired',
        ] as $method) {
            $m = $reflection->getMethod($method);
            $m->setAccessible(true);
            $m->invoke($this->controller);
        }

        $this->assertEquals(json_encode(['ok' => true]), $this->controller->output);
    }
}
