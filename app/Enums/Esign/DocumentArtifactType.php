<?php

namespace App\Enums\Esign;

enum DocumentArtifactType: string
{
    case BeforeSign = 'before_sign';
    case Attachment = 'attachment';
    case IntermediateSign = 'intermediate_sign';
    case AfterSign = 'after_sign';
    case FailedOutput = 'failed_output';
}
