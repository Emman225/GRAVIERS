<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class productRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'reference' => 'required',
            'nom' => 'required',
            'abreviation' => 'required',
            'unite' => 'required',
            'prix' => 'required',
            'description' => 'required',
            'meilleur_note' => 'required',
            'categorie' => 'required',
            'image' => 'required',
            'type_affaire'=> 'required',
            'reduction' => 'nullable'

        ];
    }
}
