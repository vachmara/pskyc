<?php

namespace Tests\PsKyc\Controller;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class CronControllerFrontTest extends MockeryTestCase
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

    public function testConstructorSetsAjaxToTrue()
    {
        $controller = new \PskycCronModuleFrontController();
        $this->assertTrue($controller->ajax);
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
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testDailyMaintenanceException()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('runDailyMaintenance')
            ->once()
            ->andThrow(new \Exception('Maintenance failed'));

        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Daily maintenance error: Maintenance failed', 3, null, 'Pskyc');

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('daily_maintenance');

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Maintenance failed: Maintenance failed', $controller->output);
    }

    public function testSendWarningsAction()
    {
        $token = 'token123';
        $results = ['warnings_sent' => 5];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('sendExpiryWarnings')
            ->once()
            ->with(30)
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('send_warnings');
        $this->toolsMock->shouldReceive('getValue')
            ->with('warning_days', 30)
            ->andReturn(30);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testSendWarningsActionWithCustomDays()
    {
        $token = 'token123';
        $results = ['warnings_sent' => 3];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('sendExpiryWarnings')
            ->once()
            ->with(7)
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('send_warnings');
        $this->toolsMock->shouldReceive('getValue')
            ->with('warning_days', 30)
            ->andReturn(7);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testSendWarningsException()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('sendExpiryWarnings')
            ->once()
            ->andThrow(new \Exception('Warning failed'));

        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Expiry warnings error: Warning failed', 3, null, 'Pskyc');

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('send_warnings');
        $this->toolsMock->shouldReceive('getValue')
            ->with('warning_days', 30)
            ->andReturn(30);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Expiry warnings failed: Warning failed', $controller->output);
    }

    public function testUpdateExpiredAction()
    {
        $token = 'token123';
        $results = ['updated' => 2];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('updateExpiredVerifications')
            ->once()
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('update_expired');

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testUpdateExpiredException()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('updateExpiredVerifications')
            ->once()
            ->andThrow(new \Exception('Update failed'));

        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Update expired error: Update failed', 3, null, 'Pskyc');

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('update_expired');

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Update expired failed: Update failed', $controller->output);
    }

    public function testCleanupDocumentsAction()
    {
        $token = 'token123';
        $results = ['deleted' => 5];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupExpiredDocuments')
            ->once()
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_documents');

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testCleanupDocumentsException()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupExpiredDocuments')
            ->once()
            ->andThrow(new \Exception('Cleanup failed'));

        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Document cleanup error: Cleanup failed', 3, null, 'Pskyc');

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_documents');

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Document cleanup failed: Cleanup failed', $controller->output);
    }

    public function testCleanupLogsAction()
    {
        $token = 'token123';
        $results = ['deleted' => 10];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupOldLogs')
            ->once()
            ->with(null)
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_logs');
        $this->toolsMock->shouldReceive('getValue')
            ->with('retention_days', 0)
            ->andReturn(0);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testCleanupLogsActionWithRetentionDays()
    {
        $token = 'token123';
        $results = ['deleted' => 8];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupOldLogs')
            ->once()
            ->with(30)
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_logs');
        $this->toolsMock->shouldReceive('getValue')
            ->with('retention_days', 0)
            ->andReturn(30);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testCleanupLogsException()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupOldLogs')
            ->once()
            ->andThrow(new \Exception('Log cleanup failed'));

        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Log cleanup error: Log cleanup failed', 3, null, 'Pskyc');

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_logs');
        $this->toolsMock->shouldReceive('getValue')
            ->with('retention_days', 0)
            ->andReturn(0);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Log cleanup failed: Log cleanup failed', $controller->output);
    }

    public function testCleanupTempAction()
    {
        $token = 'token123';
        $results = ['deleted' => 3];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupTempFiles')
            ->once()
            ->with(24)
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_temp');
        $this->toolsMock->shouldReceive('getValue')
            ->with('max_age_hours', 24)
            ->andReturn(24);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testCleanupTempActionWithCustomHours()
    {
        $token = 'token123';
        $results = ['deleted' => 1];

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupTempFiles')
            ->once()
            ->with(48)
            ->andReturn($results);

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_temp');
        $this->toolsMock->shouldReceive('getValue')
            ->with('max_age_hours', 24)
            ->andReturn(48);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame(json_encode($results), $controller->output);
    }

    public function testCleanupTempException()
    {
        $token = 'token123';

        $this->maintenanceServiceMock->shouldReceive('getCronToken')
            ->once()
            ->andReturn($token);
        $this->maintenanceServiceMock->shouldReceive('cleanupTempFiles')
            ->once()
            ->andThrow(new \Exception('Temp cleanup failed'));

        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Temp cleanup error: Temp cleanup failed', 3, null, 'Pskyc');

        $this->toolsMock->shouldReceive('getValue')
            ->with('token')
            ->andReturn($token);
        $this->toolsMock->shouldReceive('getValue')
            ->with('action', 'daily_maintenance')
            ->andReturn('cleanup_temp');
        $this->toolsMock->shouldReceive('getValue')
            ->with('max_age_hours', 24)
            ->andReturn(24);

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Temp cleanup failed: Temp cleanup failed', $controller->output);
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
        $controller->module = $this->moduleMock;
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
            ->with('Service initialization failed: no service', 3, null, 'Pskyc');
        $this->loggerMock->shouldReceive('addLog')
            ->once()
            ->with('Cron service initialization failed: MaintenanceService not available', 3, null, 'Pskyc');

        $controller = new \PskycCronModuleFrontController();
        $controller->module = $this->moduleMock;
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
        $controller->module = $this->moduleMock;
        set_error_handler(function () {});
        ob_start();
        $controller->postProcess();
        ob_end_clean();
        restore_error_handler();

        $this->assertSame('Unknown action', $controller->output);
    }
}
