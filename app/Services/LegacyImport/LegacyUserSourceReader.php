<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyUserPositionDocumentReference;
use App\Data\LegacyImport\LegacyUserRow;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

final class LegacyUserSourceReader
{
    private const string ConnectionName = 'legacy_import';

    private const string TableName = 'users';

    private const int DefaultChunkSize = 500;

    /** @var list<string> */
    private const array Columns = [
        'id',
        'name',
        'status',
        'nip',
        'nik',
        'id_jabatan',
        'id_instansi',
        'id_unit_kerja',
        'file_sk',
        'email',
        'password',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    private bool $readOnlySessionVerified = false;

    public function __construct(
        private DatabaseManager $database,
    ) {}

    public function count(): int
    {
        return $this->query()->count();
    }

    public function find(int $id): ?LegacyUserRow
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Legacy user ID must be greater than zero.');
        }

        $row = $this->query()->where('id', $id)->first();

        return $row instanceof stdClass
            ? LegacyUserRow::fromDatabaseRow($row)
            : null;
    }

    /**
     * @return LazyCollection<int, LegacyUserRow>
     */
    public function lazy(int $chunkSize = self::DefaultChunkSize): LazyCollection
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Legacy user chunk size must be greater than zero.');
        }

        return $this->query()
            ->lazyById($chunkSize, 'id')
            ->map(static fn (stdClass $row): LegacyUserRow => LegacyUserRow::fromDatabaseRow($row));
    }

    /**
     * @return LazyCollection<int, LegacyUserPositionDocumentReference>
     */
    public function lazyDocumentReferences(int $chunkSize = self::DefaultChunkSize): LazyCollection
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Legacy user chunk size must be greater than zero.');
        }

        return $this->connection()
            ->table(self::TableName)
            ->select(['id', 'file_sk'])
            ->lazyById($chunkSize, 'id')
            ->map(static fn (stdClass $row): LegacyUserPositionDocumentReference => LegacyUserPositionDocumentReference::fromDatabaseRow($row));
    }

    private function query(): Builder
    {
        return $this->connection()
            ->table(self::TableName)
            ->select(self::Columns);
    }

    private function connection(): Connection
    {
        $connection = $this->database->connection(self::ConnectionName);

        if ($this->readOnlySessionVerified) {
            return $connection;
        }

        $session = $connection->selectOne(
            'SELECT @@session.transaction_read_only AS session_read_only'
        );

        if ((int) ($session->session_read_only ?? 0) !== 1) {
            throw new RuntimeException(
                'The legacy_import database connection must use a read-only session.'
            );
        }

        $this->readOnlySessionVerified = true;

        return $connection;
    }
}
