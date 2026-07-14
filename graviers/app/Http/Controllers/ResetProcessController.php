<?php

namespace App\Http\Controllers;
use App\Models\CodeReset;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailUser;
use Illuminate\Support\Facades\Hash;
use Help;
use Illuminate\Http\Request;
use App\Models\User;

class ResetProcessController extends Controller
{
    /**
     * Liste des origines acceptées et la route de login correspondante.
     */
    private const FROM_TO_LOGIN_ROUTE = [
        'admin'        => 'show.login',
        'gestionnaire' => 'show.login',
        'client'       => 'client.login',
        'livreur'      => 'livreur.login',
        'apporteur'    => 'apporteur.login',
        'fournisseur'  => 'sellers.login',
    ];

    /**
     * Capte la valeur ?from=... arrivant en query, la valide et la persiste en session
     * pour être lue par toutes les vues du flow reset (demandeEmail → code → passwordModify).
     */
    private function captureFrom(Request $request): void
    {
        $from = strtolower((string) $request->query('from', ''));
        if ($from && array_key_exists($from, self::FROM_TO_LOGIN_ROUTE)) {
            session(['reset_from' => $from]);
        }
    }

    // Affichage de la page de demande d'email
    public function demandeEmailPage(Request $request){
        $this->captureFrom($request);
        return view('reset.demandeEmail');
    }


    public function demandeEmail(Request $request){

        // on verifie si l'email existe dans la base de données
        $user = User::where('email',$request->email)->first();

        if($user){

            session()->put([
                'email' => $request->email,
                'user_id' => $user->id,
            ]);
            try{
            // on verifie si l'utilisateur à une demande en cours ? Supprime : envoie le code
            $emailWaiting = CodeReset::where('email',$request->email)->where('utilise',0)->first();

            if($emailWaiting){
                $emailWaiting->utilise = 1;
                $emailWaiting->update();
            }

            $code = rand(1000,9999);
            $data = [
                'code' => $code,
                'email' => $request->email,
                'user_id' => $user->id,
            ];

            CodeReset::create($data);
            // envoie d'email
            Mail::send(new EmailUser($code,$request->email));



            return redirect()->route('code');
        } catch (\Throwable $th) {
            // dd($th);
            return view('layout.errorCatchBack');
        }
        }else{
            return redirect()->route('demandeEmail')->with('fail','email inexistant');
        }
    }

    // Recuperation du code de reinitialisation
    // Affichage de la page de demande de code

    public function codeResetPage(){
        return view('reset.code');
    }

    public function codeReset(Request $request){
        // dd($request->code);

        $code = CodeReset::where('code','=',$request->code)->where('utilise',0)->where('email',session('email'))->first();

        if($code){
            $email = $code->email;
            $code->utilise = 1;
            $code->update();
            // dd($email);
            return redirect()->route('passwordModify');
        }else{
            return redirect()->route('code')->with('fail','Mauvais code');
        }
    }

    public function passwordModifyPage(){
        return view('reset.passwordModify');
    }

    public function passwordModify(Request $request){

        $user = User::where('email','=',session('email'))->first();

        $user->update([
            'password' => Help::HashPassword($request->password)
        ]);

        session()->forget('email');
        session()->forget('user_id');
        session()->forget('reset_from');

        switch($user->type_user_id){
            case 2 :
                return redirect()->route('show.login')->with('modified','Connectez vous avec votre nouveau mot de passe');
            case 3 :
                return redirect()->route('show.login')->with('modified','Connectez vous avec votre nouveau mot de passe');
            case 4 :
                return redirect()->route('client.login')->with('modified','Connectez vous avec votre nouveau mot de passe');
            case 5 :
                return redirect()->route('sellers.login')->with('modified','Connectez vous avec votre nouveau mot de passe');
            case 6 :
                return redirect()->route('apporteur.login')->with('modified','Connectez vous avec votre nouveau mot de passe');
            case 8 :
                return redirect()->route('livreur.login')->with('modified','Connectez vous avec votre nouveau mot de passe');
            default:
                return redirect()->route('client.login')->with('modified','Connectez vous avec votre nouveau mot de passe');
        }
    }

    /**
     * Helper public utilisable depuis les vues Blade pour résoudre la route de login
     * correspondant à l'origine du parcours reset (session('reset_from')).
     */
    public static function resetBackLogin(): string
    {
        $from  = strtolower((string) session('reset_from', 'client'));
        $route = self::FROM_TO_LOGIN_ROUTE[$from] ?? 'client.login';
        return route($route);
    }
}
