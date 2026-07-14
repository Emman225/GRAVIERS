<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LivreurRequest extends FormRequest
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
            'nom_prenoms' => 'required',
            'email' => ['required','email'],
            'num_piece_identite' => 'required',
            'piece_recto' => ['required','image'],
            'piece_verso' => ['required','image'],
            'contact' => 'required',
            // 'password' retiré : le mot de passe est généré automatiquement
            // par storeLivreur() et envoyé au livreur par email (sécurité :
            // l'admin ne doit pas connaître le mot de passe d'un autre compte).

        ];
    }
}
