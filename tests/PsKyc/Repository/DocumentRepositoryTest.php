<?php

namespace Tests\PsKyc\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;

class DocumentRepositoryTest extends MockeryTestCase
{
    /** @var Connection */
    private $connectionMock;

    /** @var QueryBuilder */
    private $queryBuilderMock;

    /** @var DocumentRepository */
    private $repository;

    /** @var Result */
    private $resultMock;

    protected function setUp(): void
    {
        $this->connectionMock = \Mockery::mock(Connection::class);
        $this->queryBuilderMock = \Mockery::mock(QueryBuilder::class);
        $this->resultMock = \Mockery::mock(Result::class);

        $this->repository = new DocumentRepository($this->connectionMock);
    }

    public function testFindByIdReturnsDocumentWhenFound()
    {
        $documentId = 1;
        $expectedDocument = [
            'id_kyc_document' => $documentId,
            'type' => 'passport',
            'filename' => 'test.jpg',
            'status' => 'pending',
            'date_uploaded' => '2025-01-01 10:00:00',
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_document')
            ->mockQueryBuilderWhere('id_kyc_document = :id')
            ->mockQueryBuilderSetParameter('id', $documentId);

        $this->resultMock->shouldReceive('rowCount')
            ->once()
            ->andReturn(1);

        $this->resultMock->shouldReceive('fetchAssociative')
            ->once()
            ->andReturn($expectedDocument);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findById($documentId);

        $this->assertEquals($expectedDocument, $result);
    }

    public function testFindByIdReturnsNullWhenNotFound()
    {
        $documentId = 999;

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_document')
            ->mockQueryBuilderWhere('id_kyc_document = :id')
            ->mockQueryBuilderSetParameter('id', $documentId);

        $this->resultMock->shouldReceive('rowCount')
            ->once()
            ->andReturn(0);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findById($documentId);

        $this->assertNull($result);
    }

    public function testFindByVerificationIdReturnsDocuments()
    {
        $verificationId = 1;
        $expectedDocuments = [
            [
                'id_kyc_document' => 1,
                'id_kyc_verification' => $verificationId,
                'type' => 'passport',
                'side' => 'front',
            ],
            [
                'id_kyc_document' => 2,
                'id_kyc_verification' => $verificationId,
                'type' => 'passport',
                'side' => 'back',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_document')
            ->mockQueryBuilderWhere('id_kyc_verification = :verification_id')
            ->mockQueryBuilderSetParameter('verification_id', $verificationId);

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('date_uploaded', 'ASC')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedDocuments);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findByVerificationId($verificationId);

        $this->assertEquals($expectedDocuments, $result);
    }

    public function testCreateReturnsNewDocumentId()
    {
        $documentData = [
            'verification_id' => 1,
            'type' => 'passport',
            'side' => 'front',
            'filename' => 'test.jpg',
            'filesize' => 12345,
            'mime' => 'image/jpeg',
            'sha256' => 'abc123',
            'iv' => 'def456',
            'encrypted' => 1,
            'expires_at' => '2024-12-31 23:59:59',
        ];
        $expectedId = 42;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('insert')
            ->once()
            ->with('PS_kyc_document')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('values')
            ->once()
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameters')
            ->once()
            ->with($documentData)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once();

        $this->connectionMock->shouldReceive('lastInsertId')
            ->once()
            ->andReturn($expectedId);

        $result = $this->repository->create($documentData);

        $this->assertEquals($expectedId, $result);
    }

    public function testUpdateStatusReturnsTrue()
    {
        $documentId = 1;
        $status = 'approved';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_document')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('status', ':status')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_document = :document_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('status', $status)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('document_id', $documentId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateStatus($documentId, $status);

        $this->assertTrue($result);
    }

    public function testUpdateStatusWithReviewNoteAndEmployee()
    {
        $documentId = 1;
        $status = 'approved';
        $reviewNote = 'Document verified successfully';
        $employeeId = 5;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_document')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('status', ':status')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('review_note', ':review_note')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('reviewed_by', ':employee_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_document = :document_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->times(4)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateStatus($documentId, $status, $reviewNote, $employeeId);

        $this->assertTrue($result);
    }

    public function testDeleteReturnsTrue()
    {
        $documentId = 1;

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('delete')
            ->once()
            ->with('PS_kyc_document')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_document = :document_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('document_id', $documentId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->delete($documentId);

        $this->assertTrue($result);
    }

    public function testFindExpiredDocumentsReturnsExpiredDocs()
    {
        $expiredDocuments = [
            [
                'id_kyc_document' => 1,
                'expires_at' => '2023-12-31 23:59:59',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_document')
            ->mockQueryBuilderWhere('expires_at IS NOT NULL');

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('expires_at < NOW()')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expiredDocuments);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findExpiredDocuments();

        $this->assertEquals($expiredDocuments, $result);
    }

    public function testCountByVerificationIdReturnsCount()
    {
        $verificationId = 1;
        $expectedCount = 3;

        $this->mockQueryBuilderSelect('COUNT(*)')
            ->mockQueryBuilderFrom('PS_kyc_document')
            ->mockQueryBuilderWhere('id_kyc_verification = :verification_id')
            ->mockQueryBuilderSetParameter('verification_id', $verificationId);

        $this->resultMock->shouldReceive('fetchOne')
            ->once()
            ->andReturn($expectedCount);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->countByVerificationId($verificationId);

        $this->assertEquals($expectedCount, $result);
    }

    public function testFindByVerificationIdAndTypeReturnsDocuments()
    {
        $verificationId = 1;
        $documentType = 'passport';
        $expectedDocuments = [
            [
                'id_kyc_document' => 1,
                'type' => 'passport',
                'side' => 'front',
            ],
            [
                'id_kyc_document' => 2,
                'type' => 'passport',
                'side' => 'back',
            ],
        ];

        $this->mockQueryBuilderSelect()
            ->mockQueryBuilderFrom('PS_kyc_document')
            ->mockQueryBuilderWhere('id_kyc_verification = :verification_id');

        $this->queryBuilderMock->shouldReceive('andWhere')
            ->once()
            ->with('type = :document_type')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('verification_id', $verificationId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('document_type', $documentType)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('orderBy')
            ->once()
            ->with('side', 'ASC')
            ->andReturnSelf();

        $this->resultMock->shouldReceive('fetchAllAssociative')
            ->once()
            ->andReturn($expectedDocuments);

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn($this->resultMock);

        $result = $this->repository->findByVerificationIdAndType($verificationId, $documentType);

        $this->assertEquals($expectedDocuments, $result);
    }

    public function testUpdateStatusAndNoteReturnsTrue()
    {
        $documentId = 1;
        $status = 'rejected';
        $note = 'Document quality is poor';

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_document')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('status', ':status')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('set')
            ->once()
            ->with('admin_note', ':admin_note')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_document = :document_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->times(3)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateStatusAndNote($documentId, $status, $note);

        $this->assertTrue($result);
    }

    public function testUpdateDocumentFieldsReturnsTrue()
    {
        $documentId = 1;
        $fields = [
            'status' => 'approved',
            'admin_note' => 'All good',
            'reviewed_by' => 5,
        ];

        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('update')
            ->once()
            ->with('PS_kyc_document')
            ->andReturnSelf();

        foreach ($fields as $field => $value) {
            $this->queryBuilderMock->shouldReceive('set')
                ->once()
                ->with($field, ':' . $field)
                ->andReturnSelf();

            $this->queryBuilderMock->shouldReceive('setParameter')
                ->once()
                ->with($field, $value)
                ->andReturnSelf();
        }

        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with('id_kyc_document = :document_id')
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with('document_id', $documentId)
            ->andReturnSelf();

        $this->queryBuilderMock->shouldReceive('execute')
            ->once()
            ->andReturn(1);

        $result = $this->repository->updateDocumentFields($documentId, $fields);

        $this->assertTrue($result);
    }

    /**
     * Helper method to mock QueryBuilder select
     */
    private function mockQueryBuilderSelect($select = '*')
    {
        $this->connectionMock->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilderMock);

        $this->queryBuilderMock->shouldReceive('select')
            ->once()
            ->with($select)
            ->andReturnSelf();

        return $this;
    }

    /**
     * Helper method to mock QueryBuilder from
     */
    private function mockQueryBuilderFrom($table)
    {
        $this->queryBuilderMock->shouldReceive('from')
            ->once()
            ->with($table)
            ->andReturnSelf();

        return $this;
    }

    /**
     * Helper method to mock QueryBuilder where
     */
    private function mockQueryBuilderWhere($condition)
    {
        $this->queryBuilderMock->shouldReceive('where')
            ->once()
            ->with($condition)
            ->andReturnSelf();

        return $this;
    }

    /**
     * Helper method to mock QueryBuilder setParameter
     */
    private function mockQueryBuilderSetParameter($parameter, $value)
    {
        $this->queryBuilderMock->shouldReceive('setParameter')
            ->once()
            ->with($parameter, $value)
            ->andReturnSelf();

        return $this;
    }
}
