<?php

namespace App\Exceptions\Esign;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

final class EsignConfigurationException extends Exception implements ShouldntReport
{
    /**
     * @param  list<string>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct(
            'Konfigurasi BSrE eSign tidak valid: '.implode(', ', $issues).'.'
        );
    }

    /**
     * @return array{esign_configuration_issues: list<string>, esign_configuration_issue_count: int}
     */
    public function context(): array
    {
        return [
            'esign_configuration_issues' => $this->issues,
            'esign_configuration_issue_count' => count($this->issues),
        ];
    }
}
