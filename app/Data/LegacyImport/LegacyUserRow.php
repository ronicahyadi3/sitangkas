<?php

namespace App\Data\LegacyImport;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use SensitiveParameter;

final readonly class LegacyUserRow
{
    public function __construct(
        public int $id,
        public string $name,
        public int $status,
        public ?string $nip,
        public ?string $nik,
        public ?int $jabatanId,
        public ?int $instansiId,
        public ?int $unitKerjaId,
        public string $fileSk,
        public string $email,
        #[SensitiveParameter]
        private string $passwordHash,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public ?CarbonImmutable $deletedAt,
    ) {}

    public static function fromDatabaseRow(#[SensitiveParameter] object $row): self
    {
        return new self(
            id: (int) $row->id,
            name: (string) $row->name,
            status: (int) $row->status,
            nip: self::nullableString($row->nip),
            nik: self::nullableString($row->nik),
            jabatanId: self::nullableInt($row->id_jabatan),
            instansiId: self::nullableInt($row->id_instansi),
            unitKerjaId: self::nullableInt($row->id_unit_kerja),
            fileSk: (string) $row->file_sk,
            email: (string) $row->email,
            passwordHash: (string) $row->password,
            createdAt: self::nullableDateTime($row->created_at),
            updatedAt: self::nullableDateTime($row->updated_at),
            deletedAt: self::nullableDateTime($row->deleted_at),
        );
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }
}
