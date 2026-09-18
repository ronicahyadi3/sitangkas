<?php

namespace App\Actions\LegacyImport;

use App\Data\LegacyImport\LegacyUserPositionDocumentAnalysis;
use App\Services\LegacyImport\LegacyPdfInspector;
use App\Services\LegacyImport\LegacyUserSourceReader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;

final class AnalyzeLegacyUserPositionDocuments
{
    public function __construct(
        private LegacyUserSourceReader $sourceReader,
        private LegacyPdfInspector $pdfInspector,
        private Filesystem $filesystem,
    ) {}

    public function handle(int $chunkSize): LegacyUserPositionDocumentAnalysis
    {
        $sourceDirectory = $this->sourceDirectory();
        $pdfinfoBinary = $this->pdfInspector->binary();
        $references = $this->sourceReferences($chunkSize);
        $physicalFiles = $this->physicalFiles($sourceDirectory);
        $documentProjection = $this->documentProjection(
            $references['references'],
            $physicalFiles['files'],
            $physicalFiles['ambiguous_file_keys'],
        );
        $duplicateContent = $this->duplicateContentAnalysis(
            $physicalFiles['files'],
            $documentProjection['referenced_physical_file_keys'],
        );
        $source = [
            'connection' => 'legacy_import',
            'table' => 'users',
            'source_directory' => $sourceDirectory,
            'pdfinfo_binary' => $pdfinfoBinary,
            'row_count' => $references['row_count'],
            'populated_reference_count' => $references['populated_reference_count'],
            'unique_reference_count' => $references['unique_reference_count'],
            'blank_reference_row_ids' => $references['blank_reference_row_ids'],
            'unsafe_reference_row_ids' => $references['unsafe_reference_row_ids'],
            'unexpected_prefix_row_ids' => $references['unexpected_prefix_row_ids'],
            'reference_sha256' => $references['reference_sha256'],
        ];
        $physical = [
            'file_count' => count($physicalFiles['files']),
            'pdf_count' => $physicalFiles['pdf_count'],
            'strict_valid_pdf_count' => $physicalFiles['strict_valid_pdf_count'],
            'invalid_pdf_count' => $physicalFiles['invalid_pdf_count'],
            'incomplete_file_count' => $physicalFiles['incomplete_file_count'],
            'unsupported_file_count' => $physicalFiles['unsupported_file_count'],
            'symlink_file_count' => count($physicalFiles['symlink_file_names']),
            'unreadable_file_count' => count($physicalFiles['unreadable_file_names']),
            'duplicate_basename_count' => count($physicalFiles['duplicate_basenames']),
            'orphan_physical_file_count' => count($documentProjection['orphan_physical_file_names']),
            'orphan_physical_file_names' => $documentProjection['orphan_physical_file_names'],
            'invalid_pdf_file_names' => $physicalFiles['invalid_pdf_file_names'],
            'incomplete_file_names' => $physicalFiles['incomplete_file_names'],
            'symlink_file_names' => $physicalFiles['symlink_file_names'],
            'unreadable_file_names' => $physicalFiles['unreadable_file_names'],
            'duplicate_basenames' => $physicalFiles['duplicate_basenames'],
            'manifest_sha256' => $physicalFiles['manifest_sha256'],
            'duplicate_content_group_count' => $duplicateContent['physical_group_count'],
            'duplicate_content_file_count' => $duplicateContent['physical_file_count'],
            'files' => array_values($physicalFiles['files']),
        ];
        $documents = [
            'matched_reference_count' => $documentProjection['matched_reference_count'],
            'missing_reference_count' => count($documentProjection['missing_reference_row_ids']),
            'available_valid_count' => count($documentProjection['available_valid_row_ids']),
            'invalid_pdf_count' => count($documentProjection['invalid_pdf_row_ids']),
            'incomplete_reference_count' => count($documentProjection['incomplete_reference_row_ids']),
            'ambiguous_reference_count' => count($documentProjection['ambiguous_reference_row_ids']),
            'case_mismatch_count' => count($documentProjection['case_mismatch_row_ids']),
            'missing_reference_row_ids' => $documentProjection['missing_reference_row_ids'],
            'available_valid_row_ids' => $documentProjection['available_valid_row_ids'],
            'invalid_pdf_row_ids' => $documentProjection['invalid_pdf_row_ids'],
            'incomplete_reference_row_ids' => $documentProjection['incomplete_reference_row_ids'],
            'ambiguous_reference_row_ids' => $documentProjection['ambiguous_reference_row_ids'],
            'case_mismatch_row_ids' => $documentProjection['case_mismatch_row_ids'],
            'referenced_duplicate_content_group_count' => $duplicateContent['referenced_group_count'],
            'referenced_duplicate_content_file_count' => $duplicateContent['referenced_file_count'],
            'duplicate_content_groups' => $duplicateContent['referenced_groups'],
            'projections' => $documentProjection['projections'],
        ];
        [$checks, $blockers] = $this->validate($source, $physical, $documents);

        return new LegacyUserPositionDocumentAnalysis(
            generatedAt: now()->toIso8601String(),
            source: $source,
            physicalFiles: $physical,
            documents: $documents,
            checks: $checks,
            blockers: $blockers,
            warnings: $this->warnings($physical, $documents),
        );
    }

    private function sourceDirectory(): string
    {
        $configuredDirectory = trim((string) config(
            'legacy_import.position_documents.source_directory',
            '',
        ));
        $sourceDirectory = realpath($configuredDirectory);

        if ($configuredDirectory === '' || $sourceDirectory === false || ! is_dir($sourceDirectory)) {
            throw new RuntimeException('Direktori staging file SK legacy tidak ditemukan.');
        }

        if (! is_readable($sourceDirectory)) {
            throw new RuntimeException('Direktori staging file SK legacy tidak dapat dibaca.');
        }

        return $sourceDirectory;
    }

    /** @return array<string, mixed> */
    private function sourceReferences(int $chunkSize): array
    {
        $references = [];
        $referenceValues = [];
        $blankReferenceRowIds = [];
        $unsafeReferenceRowIds = [];
        $unexpectedPrefixRowIds = [];
        $fingerprint = hash_init('sha256');
        $rowCount = 0;
        $populatedReferenceCount = 0;

        foreach ($this->sourceReader->lazyDocumentReferences($chunkSize) as $reference) {
            $rowCount++;
            $normalized = $this->normalizeSourceReference($reference->sourcePath);
            hash_update($fingerprint, $reference->userPositionId.'|'.$normalized['normalized_path']."\n");

            if ($normalized['normalized_path'] === '') {
                $blankReferenceRowIds[] = $reference->userPositionId;

                continue;
            }

            $populatedReferenceCount++;
            $referenceValues[$normalized['normalized_path']] = true;

            if (! $normalized['is_safe']) {
                $unsafeReferenceRowIds[] = $reference->userPositionId;

                continue;
            }

            if (! $normalized['has_allowed_prefix']) {
                $unexpectedPrefixRowIds[] = $reference->userPositionId;

                continue;
            }

            $references[] = [
                'user_position_id' => $reference->userPositionId,
                'source_path' => $normalized['normalized_path'],
                'basename' => $normalized['basename'],
                'basename_key' => Str::lower($normalized['basename']),
            ];
        }

        return [
            'references' => $references,
            'row_count' => $rowCount,
            'populated_reference_count' => $populatedReferenceCount,
            'unique_reference_count' => count($referenceValues),
            'blank_reference_row_ids' => $this->sortedIds($blankReferenceRowIds),
            'unsafe_reference_row_ids' => $this->sortedIds($unsafeReferenceRowIds),
            'unexpected_prefix_row_ids' => $this->sortedIds($unexpectedPrefixRowIds),
            'reference_sha256' => hash_final($fingerprint),
        ];
    }

    /**
     * @return array{normalized_path: string, basename: string, is_safe: bool, has_allowed_prefix: bool}
     */
    private function normalizeSourceReference(string $sourcePath): array
    {
        $normalizedPath = trim(str_replace('\\', '/', $sourcePath));
        $segments = array_values(array_filter(
            explode('/', $normalizedPath),
            static fn (string $segment): bool => $segment !== '',
        ));
        $containsTraversal = in_array('..', $segments, true);
        $isAbsolute = str_starts_with($normalizedPath, '/')
            || preg_match('/^[A-Za-z]:\//', $normalizedPath) === 1;
        $isSafe = $normalizedPath !== ''
            && ! str_contains($normalizedPath, "\0")
            && ! $containsTraversal
            && ! $isAbsolute;
        $basename = $normalizedPath === '' ? '' : basename($normalizedPath);
        $prefix = count($segments) > 1
            ? Str::lower(implode('/', array_slice($segments, 0, -1)))
            : '';
        $allowedPrefixes = array_map(
            static fn (mixed $value): string => Str::lower(trim((string) $value, '/')),
            (array) config('legacy_import.position_documents.allowed_source_prefixes', ['', 'file_sk']),
        );

        return [
            'normalized_path' => $normalizedPath,
            'basename' => $basename,
            'is_safe' => $isSafe,
            'has_allowed_prefix' => in_array($prefix, $allowedPrefixes, true),
        ];
    }

    /** @return array<string, mixed> */
    private function physicalFiles(string $sourceDirectory): array
    {
        $files = [];
        $basenameOccurrences = [];
        $pdfCount = 0;
        $strictValidPdfCount = 0;
        $invalidPdfFileNames = [];
        $incompleteFileNames = [];
        $unsupportedFileNames = [];
        $symlinkFileNames = [];
        $unreadableFileNames = [];

        foreach ($this->filesystem->files($sourceDirectory) as $file) {
            $metadata = $this->physicalFileMetadata($file);
            $key = Str::lower($metadata['file_name']);
            $basenameOccurrences[$key][] = $metadata['file_name'];
            $files[$key] = $metadata;
            $pdfCount += $metadata['is_pdf'] ? 1 : 0;
            $strictValidPdfCount += $metadata['is_valid_pdf'] ? 1 : 0;

            if ($metadata['is_pdf'] && ! $metadata['is_valid_pdf']) {
                $invalidPdfFileNames[] = $metadata['file_name'];
            }

            if ($metadata['is_incomplete']) {
                $incompleteFileNames[] = $metadata['file_name'];
            } elseif (! $metadata['is_pdf']) {
                $unsupportedFileNames[] = $metadata['file_name'];
            }

            if ($metadata['is_symlink']) {
                $symlinkFileNames[] = $metadata['file_name'];
            }

            if (! $metadata['is_readable']) {
                $unreadableFileNames[] = $metadata['file_name'];
            }
        }

        ksort($files, SORT_STRING);
        $duplicateBasenames = array_filter(
            $basenameOccurrences,
            static fn (array $names): bool => count($names) > 1,
        );
        $manifestFingerprint = hash_init('sha256');

        foreach ($files as $file) {
            hash_update($manifestFingerprint, implode('|', [
                $file['file_name'],
                $file['size_bytes'],
                $file['modified_at_unix'],
                $file['sha256'],
                $file['mime_type'] ?? '',
                $file['is_valid_pdf'] ? '1' : '0',
            ])."\n");
        }

        return [
            'files' => $files,
            'pdf_count' => $pdfCount,
            'strict_valid_pdf_count' => $strictValidPdfCount,
            'invalid_pdf_count' => count($invalidPdfFileNames),
            'incomplete_file_count' => count($incompleteFileNames),
            'unsupported_file_count' => count($unsupportedFileNames),
            'invalid_pdf_file_names' => $this->sortedStrings($invalidPdfFileNames),
            'incomplete_file_names' => $this->sortedStrings($incompleteFileNames),
            'symlink_file_names' => $this->sortedStrings($symlinkFileNames),
            'unreadable_file_names' => $this->sortedStrings($unreadableFileNames),
            'duplicate_basenames' => $duplicateBasenames,
            'ambiguous_file_keys' => array_keys($duplicateBasenames),
            'manifest_sha256' => hash_final($manifestFingerprint),
        ];
    }

    /** @return array<string, mixed> */
    private function physicalFileMetadata(SplFileInfo $file): array
    {
        $path = $file->getPathname();
        $fileName = $file->getFilename();
        $extension = Str::lower($file->getExtension());
        $isIncomplete = Str::endsWith(Str::lower($fileName), '.filepart');
        $isPdf = $extension === 'pdf';
        $isReadable = is_readable($path);
        $sha256 = $isReadable ? hash_file('sha256', $path) : false;
        $pdfInspection = $isPdf && $isReadable
            ? $this->pdfInspector->inspect($path)
            : [
                'is_valid' => false,
                'mime_type' => $isReadable ? $this->filesystem->mimeType($path) : null,
                'has_pdf_header' => false,
                'parser_error' => null,
            ];

        return [
            'file_name' => $fileName,
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'modified_at_unix' => $file->getMTime(),
            'mime_type' => is_string($pdfInspection['mime_type']) ? $pdfInspection['mime_type'] : null,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'is_pdf' => $isPdf,
            'is_valid_pdf' => $isPdf && $pdfInspection['is_valid'],
            'has_pdf_header' => $pdfInspection['has_pdf_header'],
            'parser_error' => $pdfInspection['parser_error'],
            'is_incomplete' => $isIncomplete,
            'is_symlink' => $file->isLink(),
            'is_readable' => $isReadable && is_string($sha256),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $references
     * @param  array<string, array<string, mixed>>  $physicalFiles
     * @param  list<string>  $ambiguousFileKeys
     * @return array<string, mixed>
     */
    private function documentProjection(
        array $references,
        array $physicalFiles,
        array $ambiguousFileKeys,
    ): array {
        $physicalKeys = array_fill_keys(array_keys($physicalFiles), true);
        $ambiguousKeys = array_fill_keys($ambiguousFileKeys, true);
        $referencedPhysicalFileKeys = [];
        $missingReferenceRowIds = [];
        $availableValidRowIds = [];
        $invalidPdfRowIds = [];
        $incompleteReferenceRowIds = [];
        $ambiguousReferenceRowIds = [];
        $caseMismatchRowIds = [];
        $projections = [];
        $matchedReferenceCount = 0;

        foreach ($references as $reference) {
            $physicalFile = $physicalFiles[$reference['basename_key']] ?? null;
            $classification = 'missing';

            if ($physicalFile === null) {
                $missingReferenceRowIds[] = $reference['user_position_id'];
            } else {
                $matchedReferenceCount++;
                $referencedPhysicalFileKeys[$reference['basename_key']] = true;
                unset($physicalKeys[$reference['basename_key']]);

                if ($physicalFile['file_name'] !== $reference['basename']) {
                    $caseMismatchRowIds[] = $reference['user_position_id'];
                }

                if (isset($ambiguousKeys[$reference['basename_key']])) {
                    $classification = 'ambiguous';
                    $ambiguousReferenceRowIds[] = $reference['user_position_id'];
                } elseif ($physicalFile['is_incomplete']) {
                    $classification = 'incomplete';
                    $incompleteReferenceRowIds[] = $reference['user_position_id'];
                } elseif (! $physicalFile['is_valid_pdf']) {
                    $classification = 'invalid_pdf';
                    $invalidPdfRowIds[] = $reference['user_position_id'];
                } else {
                    $classification = 'available_valid';
                    $availableValidRowIds[] = $reference['user_position_id'];
                }
            }

            $projections[] = [
                'user_position_id' => $reference['user_position_id'],
                'classification' => $classification,
                'source_path' => $reference['source_path'],
                'physical_file_name' => $physicalFile['file_name'] ?? null,
                'size_bytes' => $physicalFile['size_bytes'] ?? null,
                'mime_type' => $physicalFile['mime_type'] ?? null,
                'sha256' => $physicalFile['sha256'] ?? null,
            ];
        }

        return [
            'matched_reference_count' => $matchedReferenceCount,
            'missing_reference_row_ids' => $this->sortedIds($missingReferenceRowIds),
            'available_valid_row_ids' => $this->sortedIds($availableValidRowIds),
            'invalid_pdf_row_ids' => $this->sortedIds($invalidPdfRowIds),
            'incomplete_reference_row_ids' => $this->sortedIds($incompleteReferenceRowIds),
            'ambiguous_reference_row_ids' => $this->sortedIds($ambiguousReferenceRowIds),
            'case_mismatch_row_ids' => $this->sortedIds($caseMismatchRowIds),
            'orphan_physical_file_names' => $this->sortedStrings(array_map(
                static fn (string $key): string => (string) $physicalFiles[$key]['file_name'],
                array_keys($physicalKeys),
            )),
            'referenced_physical_file_keys' => array_keys($referencedPhysicalFileKeys),
            'projections' => $projections,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $physicalFiles
     * @param  list<string>  $referencedPhysicalFileKeys
     * @return array<string, mixed>
     */
    private function duplicateContentAnalysis(array $physicalFiles, array $referencedPhysicalFileKeys): array
    {
        $physicalGroups = [];
        $referencedGroups = [];
        $referencedKeys = array_fill_keys($referencedPhysicalFileKeys, true);

        foreach ($physicalFiles as $key => $file) {
            if (! $file['is_pdf'] || $file['sha256'] === null) {
                continue;
            }

            $physicalGroups[$file['sha256']][] = $file['file_name'];

            if (isset($referencedKeys[$key])) {
                $referencedGroups[$file['sha256']][] = $file['file_name'];
            }
        }

        $physicalDuplicates = $this->duplicateGroups($physicalGroups);
        $referencedDuplicates = $this->duplicateGroups($referencedGroups);

        return [
            'physical_group_count' => count($physicalDuplicates),
            'physical_file_count' => array_sum(array_map('count', $physicalDuplicates)),
            'referenced_group_count' => count($referencedDuplicates),
            'referenced_file_count' => array_sum(array_map('count', $referencedDuplicates)),
            'referenced_groups' => array_map(
                static fn (array $files, string $sha256): array => [
                    'sha256' => $sha256,
                    'file_names' => $files,
                ],
                $referencedDuplicates,
                array_keys($referencedDuplicates),
            ),
        ];
    }

    /**
     * @param  array<string, list<string>>  $groups
     * @return array<string, list<string>>
     */
    private function duplicateGroups(array $groups): array
    {
        $groups = array_filter($groups, static fn (array $files): bool => count($files) > 1);

        foreach ($groups as &$files) {
            sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($files);

        ksort($groups, SORT_STRING);

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $physical
     * @param  array<string, mixed>  $documents
     * @return array{list<array{name: string, expected: int|string, actual: int|string, passed: bool}>, list<string>}
     */
    private function validate(array $source, array $physical, array $documents): array
    {
        $checks = [];
        $blockers = [];
        $expected = config('legacy_import.position_documents.expected', []);
        $snapshotChecks = [
            'source.row_count' => ['source_row_count', $source['row_count']],
            'source.populated_reference_count' => ['populated_reference_count', $source['populated_reference_count']],
            'source.unique_reference_count' => ['unique_reference_count', $source['unique_reference_count']],
            'physical.file_count' => ['physical_file_count', $physical['file_count']],
            'physical.pdf_count' => ['physical_pdf_count', $physical['pdf_count']],
            'physical.incomplete_file_count' => ['physical_incomplete_count', $physical['incomplete_file_count']],
            'documents.matched_reference_count' => ['matched_reference_count', $documents['matched_reference_count']],
            'documents.missing_reference_count' => ['missing_reference_count', $documents['missing_reference_count']],
            'documents.available_valid_count' => ['available_valid_count', $documents['available_valid_count']],
            'documents.invalid_pdf_count' => ['invalid_pdf_count', $documents['invalid_pdf_count']],
            'documents.incomplete_reference_count' => ['incomplete_reference_count', $documents['incomplete_reference_count']],
            'physical.orphan_file_count' => ['orphan_physical_file_count', $physical['orphan_physical_file_count']],
            'physical.duplicate_content_group_count' => ['physical_duplicate_content_group_count', $physical['duplicate_content_group_count']],
            'physical.duplicate_content_file_count' => ['physical_duplicate_content_file_count', $physical['duplicate_content_file_count']],
            'documents.duplicate_content_group_count' => ['referenced_duplicate_content_group_count', $documents['referenced_duplicate_content_group_count']],
            'documents.duplicate_content_file_count' => ['referenced_duplicate_content_file_count', $documents['referenced_duplicate_content_file_count']],
            'source.reference_sha256' => ['reference_sha256', $source['reference_sha256']],
            'physical.manifest_sha256' => ['manifest_sha256', $physical['manifest_sha256']],
        ];

        foreach ($snapshotChecks as $name => [$configKey, $actual]) {
            $expectedValue = $expected[$configKey] ?? null;

            if ($expectedValue !== null) {
                $this->addCheck($checks, $blockers, $name, $expectedValue, $actual, 'snapshot file SK');
            }
        }

        foreach ([
            'source.blank_references' => count($source['blank_reference_row_ids']),
            'source.unsafe_references' => count($source['unsafe_reference_row_ids']),
            'source.unexpected_prefixes' => count($source['unexpected_prefix_row_ids']),
            'physical.symlinks' => $physical['symlink_file_count'],
            'physical.unreadable_files' => $physical['unreadable_file_count'],
            'physical.duplicate_basenames' => $physical['duplicate_basename_count'],
            'documents.ambiguous_references' => $documents['ambiguous_reference_count'],
            'documents.case_mismatches' => $documents['case_mismatch_count'],
            'documents.unaccounted_references' => $source['populated_reference_count']
                - $documents['available_valid_count']
                - $documents['missing_reference_count']
                - $documents['invalid_pdf_count']
                - $documents['incomplete_reference_count']
                - $documents['ambiguous_reference_count'],
        ] as $name => $actual) {
            $this->addCheck($checks, $blockers, $name, 0, $actual, 'invariant analyzer file SK');
        }

        return [$checks, $blockers];
    }

    /**
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     */
    private function addCheck(
        array &$checks,
        array &$blockers,
        string $name,
        int|string $expected,
        int|string $actual,
        string $rule,
    ): void {
        $passed = $expected === $actual;
        $checks[] = compact('name', 'expected', 'actual', 'passed');

        if (! $passed) {
            $blockers[] = "{$name} tidak sesuai {$rule}.";
        }
    }

    /**
     * @param  array<string, mixed>  $physical
     * @param  array<string, mixed>  $documents
     * @return list<string>
     */
    private function warnings(array $physical, array $documents): array
    {
        $warnings = [];
        $this->addWarning($warnings, $documents['missing_reference_count'], 'Ada referensi file SK tanpa file fisik; row tersebut akan dilewati.');
        $this->addWarning($warnings, $documents['invalid_pdf_count'], 'Ada referensi file SK dengan PDF tidak valid; row tersebut akan dilewati.');
        $this->addWarning($warnings, $documents['incomplete_reference_count'], 'Ada referensi file SK partial/incomplete; row tersebut akan dilewati.');
        $this->addWarning($warnings, $physical['orphan_physical_file_count'], 'Ada file fisik yang tidak direferensikan oleh legacy users; file tersebut tidak akan ditebak kepemilikannya.');
        $this->addWarning($warnings, $documents['referenced_duplicate_content_group_count'], 'Ada file SK terreferensi dengan konten yang sama; duplikasi dicatat tanpa dihapus otomatis.');

        return $warnings;
    }

    /** @param list<string> $warnings */
    private function addWarning(array &$warnings, int $count, string $message): void
    {
        if ($count > 0) {
            $warnings[] = $message;
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function sortedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sortedStrings(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }
}
