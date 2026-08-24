<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Everyone may attempt a purchase; the achievements assessment does not
     * define an authentication/authorization scheme, so this is left open.
     * See the README's "Known limitations" section.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, array{product_id: int, quantity: int}>
     */
    public function items(): array
    {
        return $this->validated('items');
    }
}
