<?php

namespace App\Enums\Esign;

enum DocumentArtifactDecorationScope: string
{
    case AllPages = 'all_pages';
    case SelectedPages = 'selected_pages';
}
