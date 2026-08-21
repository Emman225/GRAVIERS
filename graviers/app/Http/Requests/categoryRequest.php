<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class categoryRequest extends FormRequest
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
            'nom'         => 'required|max:50',
            'description' => 'required|max:255',
            // L'IMAGE N'ÉTAIT PAS VALIDÉE DU TOUT, et le contrôleur appelait
            // pourtant store() dessus sans détour : sans fichier, la page
            // tombait en erreur 500 sans un mot d'explication. Elle reste
            // facultative — la colonne accepte le vide — mais ce qui est
            // envoyé est désormais contrôlé.
            'image'       => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            // Aucun parent = catégorie racine (convention : 0).
            'parent'      => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'         => 'Le nom de la catégorie est obligatoire.',
            'nom.max'              => 'Le nom ne doit pas dépasser 50 caractères.',
            'description.required' => 'La description est obligatoire.',
            'description.max'      => 'La description ne doit pas dépasser 255 caractères.',
            'image.image'          => 'Le fichier choisi doit être une image.',
            'image.mimes'          => 'L\'image doit être au format JPEG, PNG ou WEBP.',
            'image.max'            => 'L\'image ne doit pas dépasser 2 Mo.',
        ];
    }
}
