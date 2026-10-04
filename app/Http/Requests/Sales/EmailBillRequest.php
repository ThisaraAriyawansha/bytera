<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class EmailBillRequest extends FormRequest
{
    /**
     * The largest bill PDF accepted, in bytes.
     */
    public const MAX_PDF_BYTES = 10 * 1024 * 1024;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::any(['sales.view', 'bills.view']);
    }

    /**
     * Get the validation rules that apply to the request. Only the PDF comes from the browser; the sale
     * and the recipient are loaded on the server.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pdf' => ['required', 'string', 'max:'.(int) ceil(self::MAX_PDF_BYTES * 4 / 3) + 4],
        ];
    }

    /**
     * Get the "after" validation callables.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && $this->pdfBytes() === null) {
                    $validator->errors()->add('pdf', 'The bill PDF could not be read. Please try again.');
                }
            },
        ];
    }

    /**
     * Decode the base64 PDF (a `data:` URL prefix is allowed). Null when it isn't a PDF.
     */
    public function pdfBytes(): ?string
    {
        $encoded = preg_replace('/^data:application\/pdf;(?:[^,]*;)?base64,/', '', (string) $this->input('pdf'));
        $bytes = base64_decode((string) $encoded, true);

        return is_string($bytes) && str_starts_with($bytes, '%PDF') && strlen($bytes) <= self::MAX_PDF_BYTES ? $bytes : null;
    }
}
