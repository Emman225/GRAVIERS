<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApporteurRequest extends FormRequest
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

            'nom_prenom' => 'required',
            'adresse' => 'required',
            'email' => 'required',
            'contact' => 'required',
            'password' => 'required',
            'recto' => 'required|mimes:png,jpg|max: 2048',
            'verso' => 'required|mimes:png,jpg|max:2048',

        ];
    }
}
