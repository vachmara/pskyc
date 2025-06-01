<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */

namespace Tests\PsKyc\Service;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\CustomerRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\NotificationService;
use PrestaShop\Module\Pskyc\Service\VerificationService;

class VerificationServiceTest extends MockeryTestCase
{
    /** @var VerificationRepository */
    private $verificationRepositoryMock;

    /** @var DocumentRepository */
    private $documentRepositoryMock;

    /** @var LogRepository */
    private $logRepositoryMock;

    /** @var DocumentService */
    private $documentServiceMock;

    /** @var NotificationService */
    private $notificationServiceMock;

    /** @var CustomerRepository */
    private $customerRepositoryMock;

    /** @var VerificationService */
    private $service;

    protected function setUp(): void
    {
        $this->verificationRepositoryMock = \Mockery::mock(VerificationRepository::class);
        $this->documentRepositoryMock = \Mockery::mock(DocumentRepository::class);
        $this->logRepositoryMock = \Mockery::mock(LogRepository::class);
        $this->notificationServiceMock = \Mockery::mock(NotificationService::class);
        $this->customerRepositoryMock = \Mockery::mock(CustomerRepository::class);

        $this->service = new VerificationService(
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->logRepositoryMock,
            $this->notificationServiceMock,
            $this->customerRepositoryMock
        );

        // Mock static classes
        \Configuration::setStaticExpectations(\Mockery::mock());
        \PrestaShopLogger::setStaticExpectations(\Mockery::mock());

        // Mock server variables
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    public function testConstructorSetsAllDependencies()
    {
        $service = new VerificationService(
            $this->verificationRepositoryMock,
            $this->documentRepositoryMock,
            $this->logRepositoryMock,
            $this->notificationServiceMock,
            $this->customerRepositoryMock
        );

        $this->assertInstanceOf(VerificationService::class, $service);
    }

    

    public function testCreateVerificationWithActiveVerificationExists()
    {
        $customerId = 1;
        $existingVerification = ['id_kyc_verification' => 456];

        $this->verificationRepositoryMock->shouldReceive('findActiveByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn([$existingVerification]);

        $result = $this->service->createVerification($customerId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Customer already has an active verification request', $result['message']);
        $this->assertEquals(456, $result['verification_id']);
    }

    public function testCreateVerificationFailsToCreate()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findActiveByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn([]);

        $this->verificationRepositoryMock->shouldReceive('create')
            ->once()
            ->andReturn(false);

        $result = $this->service->createVerification($customerId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to create verification request', $result['message']);
    }

    public function testCreateVerificationWithException()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findActiveByCustomerId')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Verification creation error: Database error', 3, null, 'Pskyc');

        $result = $this->service->createVerification($customerId);

        $this->assertFalse($result['success']);
        $this->assertEquals('System error occurred while creating verification', $result['message']);
    }

    public function testGetVerificationsSuccessfully()
    {
        $filters = ['status' => 'pending'];
        $limit = 10;
        $offset = 0;
        $verifications = [
            ['id_kyc_verification' => 1, 'date_expiry' => null, 'is_expired' => false],
            ['id_kyc_verification' => 2, 'date_expiry' => '2025-12-31 23:59:59', 'is_expired' => false],
        ];
        $totalCount = 25;

        $this->verificationRepositoryMock->shouldReceive('findAll')
            ->once()
            ->with($filters, $limit, $offset)
            ->andReturn($verifications);

        $this->verificationRepositoryMock->shouldReceive('countAll')
            ->once()
            ->with($filters)
            ->andReturn($totalCount);

        $result = $this->service->getVerifications($filters, $limit, $offset);

        $this->assertEquals($verifications, $result['verifications']);
        $this->assertEquals($totalCount, $result['total_count']);
        $this->assertFalse($result['verifications'][0]['is_expired']);
        $this->assertFalse($result['verifications'][1]['is_expired']);
    }

    public function testGetVerificationsWithExpiredVerification()
    {
        $verifications = [
            ['id_kyc_verification' => 1, 'date_expiry' => '2020-01-01 00:00:00'],
        ];

        $this->verificationRepositoryMock->shouldReceive('findAll')
            ->once()
            ->andReturn($verifications);

        $this->verificationRepositoryMock->shouldReceive('countAll')
            ->once()
            ->andReturn(1);

        $result = $this->service->getVerifications();

        $this->assertTrue($result['verifications'][0]['is_expired']);
    }

    public function testGetVerificationsWithException()
    {
        $this->verificationRepositoryMock->shouldReceive('findAll')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Get verifications error: Database error', 3, null, 'Pskyc');

        $result = $this->service->getVerifications();

        $this->assertEquals([], $result['verifications']);
        $this->assertEquals(0, $result['total_count']);
    }

    public function testGetVerificationWithDocumentsSuccessfully()
    {
        $verificationId = 1;
        $verification = ['id_kyc_verification' => $verificationId];
        $documents = [['id_document' => 1], ['id_document' => 2]];

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($verificationId)
            ->andReturn($verification);

        $this->documentRepositoryMock->shouldReceive('findByVerificationId')
            ->once()
            ->with($verificationId)
            ->andReturn($documents);

        $result = $this->service->getVerificationWithDocuments($verificationId);

        $this->assertEquals($verification['id_kyc_verification'], $result['id_kyc_verification']);
        $this->assertEquals($documents, $result['documents']);
    }

    public function testGetVerificationWithDocumentsNotFound()
    {
        $verificationId = 999;

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($verificationId)
            ->andReturn(null);

        $result = $this->service->getVerificationWithDocuments($verificationId);

        $this->assertNull($result);
    }

    public function testGetVerificationWithDocumentsException()
    {
        $verificationId = 1;

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Get verification error: Database error', 3, null, 'Pskyc');

        $result = $this->service->getVerificationWithDocuments($verificationId);

        $this->assertNull($result);
    }

    public function testGetMostRecentVerificationSuccessfully()
    {
        $customerId = 1;
        $verifications = [['id_kyc_verification' => 1]];

        $this->verificationRepositoryMock->shouldReceive('findByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn($verifications);

        $result = $this->service->getMostRecentVerification($customerId);

        $this->assertEquals($verifications, $result);
    }

    public function testGetMostRecentVerificationWithNull()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn(null);

        $result = $this->service->getMostRecentVerification($customerId);

        $this->assertEquals([], $result);
    }

    public function testGetMostRecentVerificationWithException()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findByCustomerId')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Get customer verifications error: Database error', 3, null, 'Pskyc');

        $result = $this->service->getMostRecentVerification($customerId);

        $this->assertEquals([], $result);
    }

    public function testGetStatusCountsSuccessfully()
    {
        $statusCounts = ['pending' => 5, 'approved' => 10, 'rejected' => 2];

        $this->verificationRepositoryMock->shouldReceive('getStatusCounts')
            ->once()
            ->andReturn($statusCounts);

        $result = $this->service->getStatusCounts();

        $this->assertEquals($statusCounts, $result);
    }

    public function testGetStatusCountsWithException()
    {
        $this->verificationRepositoryMock->shouldReceive('getStatusCounts')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Get status counts error: Database error', 3, null, 'Pskyc');

        $result = $this->service->getStatusCounts();

        $this->assertEquals([], $result);
    }

    public function testGetVerificationsByCustomerIdSuccessfully()
    {
        $customerId = 1;
        $verifications = [
            ['id_kyc_verification' => 1, 'date_expiry' => null, 'is_expired' => false],
            ['id_kyc_verification' => 2, 'date_expiry' => '2025-12-31 23:59:59', 'is_expired' => false],
        ];

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn($verifications);

        $result = $this->service->getVerificationsByCustomerId($customerId);

        $this->assertEquals($verifications, $result);
        $this->assertFalse($result[0]['is_expired']);
        $this->assertFalse($result[1]['is_expired']);
    }

    public function testGetVerificationsByCustomerIdNotFound()
    {
        $customerId = 999;

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn([]);

        $result = $this->service->getVerificationsByCustomerId($customerId);

        $this->assertNull($result);
    }

    public function testGetVerificationsByCustomerIdWithException()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Get verifications by customer ID error: Database error', 3, null, 'Pskyc');

        $result = $this->service->getVerificationsByCustomerId($customerId);

        $this->assertNull($result);
    }

    public function testUpdateAdminNoteSuccessfully()
    {
        $verificationId = 1;
        $note = 'Admin updated note';
        $verification = ['id_kyc_verification' => $verificationId];

        $this->verificationRepositoryMock->shouldReceive('updateNote')
            ->once()
            ->with($verificationId, $note)
            ->andReturn(true);

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($verificationId)
            ->andReturn($verification);

        $this->logRepositoryMock->shouldReceive('createLog')
            ->once()
            ->with(
                $verificationId,
                null,
                null,
                'admin_note_updated',
                'Admin note updated: ' . $note,
                '127.0.0.1',
                'Test User Agent'
            );

        $result = $this->service->updateAdminNote($verificationId, $note);

        $this->assertTrue($result);
    }

    public function testUpdateAdminNoteFailure()
    {
        $verificationId = 1;
        $note = 'Admin updated note';

        $this->verificationRepositoryMock->shouldReceive('updateNote')
            ->once()
            ->with($verificationId, $note)
            ->andReturn(false);

        $result = $this->service->updateAdminNote($verificationId, $note);

        $this->assertFalse($result);
    }

    public function testUpdateAdminNoteWithException()
    {
        $verificationId = 1;
        $note = 'Admin updated note';

        $this->verificationRepositoryMock->shouldReceive('updateNote')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Update admin note error: Database error', 3, null, 'Pskyc');

        $result = $this->service->updateAdminNote($verificationId, $note);

        $this->assertFalse($result);
    }

    public function testUpdateStatusSuccessfully()
    {
        $verificationId = 1;
        $newStatus = 'approved';
        $note = 'Approved by admin';
        $verification = [
            'id_kyc_verification' => $verificationId,
            'id_customer' => 123,
            'status' => 'pending',
        ];
        $customerData = ['id_customer' => 123, 'email' => 'test@example.com'];

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($verificationId)
            ->andReturn($verification);

        $this->verificationRepositoryMock->shouldReceive('updateStatus')
            ->once()
            ->with($verificationId, $newStatus, $note)
            ->andReturn(true);

        $this->logRepositoryMock->shouldReceive('createLog')
            ->once();

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($verificationId)
            ->andReturn(array_merge($verification, ['status' => $newStatus]));

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->with(123)
            ->andReturn($customerData);

        $this->notificationServiceMock->shouldReceive('sendStatusChangeNotification')
            ->once();

        \Configuration::shouldReceive('get')
            ->once()
            ->with('PSKYC_RETENTION_DAYS')
            ->andReturn(365);

        $this->verificationRepositoryMock->shouldReceive('updateExpiryDate')
            ->once()
            ->with($verificationId, \Mockery::pattern('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/'));

        $result = $this->service->updateStatus($verificationId, $newStatus, $note);

        $this->assertTrue($result);
    }

    public function testUpdateStatusVerificationNotFound()
    {
        $verificationId = 999;
        $newStatus = 'approved';

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->with($verificationId)
            ->andReturn(null);

        $result = $this->service->updateStatus($verificationId, $newStatus, null);

        $this->assertFalse($result);
    }

    public function testUpdateStatusWithExpiryNull()
    {
        $verificationId = 1;
        $newStatus = 'approved';
        $verification = [
            'id_kyc_verification' => $verificationId,
            'id_customer' => 123,
            'status' => 'pending',
        ];

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->andReturn($verification);

        $this->verificationRepositoryMock->shouldReceive('updateStatus')
            ->once()
            ->andReturn(true);

        $this->logRepositoryMock->shouldReceive('createLog')
            ->once();

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->andReturn(array_merge($verification, ['status' => $newStatus]));

        $this->customerRepositoryMock->shouldReceive('getCustomerData')
            ->once()
            ->andReturn(['id_customer' => 123]);

        $this->notificationServiceMock->shouldReceive('sendStatusChangeNotification')
            ->once();

        \Configuration::shouldReceive('get')
            ->once()
            ->with('PSKYC_RETENTION_DAYS')
            ->andReturn(0);

        $this->verificationRepositoryMock->shouldReceive('updateExpiryDate')
            ->once()
            ->with($verificationId, null);

        $result = $this->service->updateStatus($verificationId, $newStatus, null);

        $this->assertTrue($result);
    }

    public function testUpdateStatusWithException()
    {
        $verificationId = 1;
        $newStatus = 'approved';

        $this->verificationRepositoryMock->shouldReceive('findById')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Update status error: Database error', 3, null, 'Pskyc');

        $result = $this->service->updateStatus($verificationId, $newStatus, null);

        $this->assertFalse($result);
    }

    public function testDeleteVerificationsByCustomerIdSuccessfully()
    {
        $customerId = 1;
        $verifications = [
            ['id_kyc_verification' => 1],
            ['id_kyc_verification' => 2],
        ];

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn($verifications);

        $this->documentRepositoryMock->shouldReceive('deleteByVerificationId')
            ->twice();

        $this->verificationRepositoryMock->shouldReceive('delete')
            ->twice();

        $this->logRepositoryMock->shouldReceive('createLog')
            ->twice();

        $result = $this->service->deleteVerificationsByCustomerId($customerId);

        $this->assertTrue($result);
    }

    public function testDeleteVerificationsByCustomerIdNoVerifications()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn([]);

        $result = $this->service->deleteVerificationsByCustomerId($customerId);

        $this->assertTrue($result);
    }

    public function testDeleteVerificationsByCustomerIdWithException()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Delete verifications error: Database error', 3, null, 'Pskyc');

        $result = $this->service->deleteVerificationsByCustomerId($customerId);

        $this->assertFalse($result);
    }

    public function testGetGdprDataSuccessfully()
    {
        $customerId = 1;
        $verifications = [
            ['id_kyc_verification' => 1],
            ['id_kyc_verification' => 2],
        ];
        $documents1 = [['id_document' => 1]];
        $documents2 = [['id_document' => 2]];

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn($verifications);

        $this->documentRepositoryMock->shouldReceive('findByVerificationId')
            ->once()
            ->with(1)
            ->andReturn($documents1);

        $this->documentRepositoryMock->shouldReceive('findByVerificationId')
            ->once()
            ->with(2)
            ->andReturn($documents2);

        $result = $this->service->getGdprData($customerId);

        $this->assertCount(2, $result);
        $this->assertEquals($verifications[0], $result[0]['verification']);
        $this->assertEquals($documents1, $result[0]['documents']);
        $this->assertEquals($verifications[1], $result[1]['verification']);
        $this->assertEquals($documents2, $result[1]['documents']);
    }

    public function testGetGdprDataNoVerifications()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->with($customerId)
            ->andReturn([]);

        $result = $this->service->getGdprData($customerId);

        $this->assertNull($result);
    }

    public function testGetGdprDataWithException()
    {
        $customerId = 1;

        $this->verificationRepositoryMock->shouldReceive('findAllByCustomerId')
            ->once()
            ->andThrow(new \Exception('Database error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Get GDPR data error: Database error', 3, null, 'Pskyc');

        $result = $this->service->getGdprData($customerId);

        $this->assertNull($result);
    }

    public function testLogActionWithMissingServerVariables()
    {
        // Remove server variables to test defaults
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);

        $verificationId = 1;
        $customerId = 123;

        $this->verificationRepositoryMock->shouldReceive('findActiveByCustomerId')
            ->once()
            ->andReturn([]);

        $this->verificationRepositoryMock->shouldReceive('create')
            ->once()
            ->andReturn($verificationId);

        $this->logRepositoryMock->shouldReceive('createLog')
            ->once()
            ->with(
                $verificationId,
                null,
                $customerId,
                'verification_created',
                'New verification request created',
                '',
                'Unknown'
            );

        $this->service->createVerification($customerId);
    }

    public function testLogActionWithException()
    {
        $verificationId = 1;
        $customerId = 123;

        $this->verificationRepositoryMock->shouldReceive('findActiveByCustomerId')
            ->once()
            ->andReturn([]);

        $this->verificationRepositoryMock->shouldReceive('create')
            ->once()
            ->andReturn($verificationId);

        $this->logRepositoryMock->shouldReceive('createLog')
            ->once()
            ->andThrow(new \Exception('Log error'));

        \PrestaShopLogger::shouldReceive('addLog')
            ->once()
            ->with('Log action error: Log error', 3, null, 'Pskyc');

        $result = $this->service->createVerification($customerId);

        $this->assertTrue($result['success']);
    }
}
