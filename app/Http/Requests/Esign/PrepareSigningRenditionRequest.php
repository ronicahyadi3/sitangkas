<?php

namespace App\Http\Requests\Esign;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrepareSigningRenditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'placements' => ['required', 'array', 'min:1', 'max:20'],
            'placements.*' => ['required', 'array:client_id,operation_index,page,page_width,page_height,page_rotation,origin_x,origin_y,width,height'],
            'placements.*.client_id' => ['required', 'uuid', 'distinct:strict'],
            'placements.*.operation_index' => ['required', 'integer', 'min:0', 'max:19', 'distinct:strict'],
            'placements.*.page' => ['required', 'integer', 'min:1'],
            'placements.*.page_width' => ['required', 'numeric', 'gt:0'],
            'placements.*.page_height' => ['required', 'numeric', 'gt:0'],
            'placements.*.page_rotation' => ['required', 'integer', Rule::in([0, 90, 180, 270])],
            'placements.*.origin_x' => ['required', 'numeric', 'gte:0'],
            'placements.*.origin_y' => ['required', 'numeric', 'gte:0'],
            'placements.*.width' => ['required', 'numeric', 'gt:0'],
            'placements.*.height' => ['required', 'numeric', 'gt:0'],
            'footer' => ['nullable', 'array:text,font_key,font_size_pt,is_bold,is_italic,is_underline,placements'],
            'footer.text' => ['required_with:footer', 'string', 'max:1000'],
            'footer.font_key' => ['required_with:footer', 'string', 'max:50'],
            'footer.font_size_pt' => ['required_with:footer', 'numeric', 'gt:0'],
            'footer.is_bold' => ['required_with:footer', 'boolean'],
            'footer.is_italic' => ['required_with:footer', 'boolean'],
            'footer.is_underline' => ['required_with:footer', 'boolean'],
            'footer.placements' => ['required_with:footer', 'array', 'min:1'],
            'footer.placements.*' => ['required', 'array:page,page_width,page_height,page_rotation,origin_x,origin_y,width,height'],
            'footer.placements.*.page' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'footer.placements.*.page_width' => ['required', 'numeric', 'gt:0'],
            'footer.placements.*.page_height' => ['required', 'numeric', 'gt:0'],
            'footer.placements.*.page_rotation' => ['required', 'integer', Rule::in([0, 90, 180, 270])],
            'footer.placements.*.origin_x' => ['required', 'numeric', 'gte:0'],
            'footer.placements.*.origin_y' => ['required', 'numeric', 'gte:0'],
            'footer.placements.*.width' => ['required', 'numeric', 'gt:0'],
            'footer.placements.*.height' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, mixed> */
    public function signingPlan(): array
    {
        return $this->validated();
    }
}
