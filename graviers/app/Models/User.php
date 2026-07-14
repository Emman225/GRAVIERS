<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Help;
use \Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Support\Str;
use Illuminate\Notifications\Notifiable;
use App\Models\Fournisseur;
use App\Models\TypeUser;
use App\Models\DemandeCompteClientATerme;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Cviebrock\EloquentSluggable\Sluggable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Ville;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, Sluggable,HasRoles;


    protected $fillable = [
        'nom_prenoms',
        'email', 
        'contact',
        'login',
        'password',
        'photo',
        'adresse',
        'type_user_id',
        'statut',
        'token',
        'ville_id',
        'deleted_at'
    ];



    public function ImagePath(){
        return Storage::url($this->photo);
    }

    public static function lire($id)
    {
        $obj = User::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new User();
    }

    public static function lireSurLogin($login)
    {
        $obj = User::where('login', $login)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new User();
    }

    public static function liste($type_user_id = null)
    {
        return User::orderBy('nom_prenoms', 'asc')
            ->when($type_user_id, function ($query) use ($type_user_id) {
                $query->where('type_user_id', $type_user_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new User($arr);
        if ($obj->save()) return $obj;
        else return new User();
    }

    public static function supprimer($id)
    {
        $obj = User::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public static function activerDesactiver($id)
    {
        $obj = User::lire($id);
        $obj->statut = $obj->statut == Help::$STATUT_INACTIF ? Help::$STATUT_ACTIF : Help::$STATUT_INACTIF;
        $obj->save();
        return $obj;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $guarded = [];

    public function getVille(): BelongsTo{
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    public function getClient(): HasOne{
        return $this->hasOne(Ville::class, 'utilisateur_id');
    }

    public function livreur (){
        return $this->hasOne(Livreur::class);
    }
    public function sluggable(): array
    {
        return [
            'login' => [
                'source' => ['fournisseur.nom_prenoms']
            ]
        ];
    }
    public function getFournisseur(): HasOne{
        return $this->hasOne(Fournisseur::class, 'user_id');
    }

    public function getApporteur(): HasOne{
        return $this->hasOne(Apporteur::class, 'user_id');
    }

    public function fournisseur(){
        return $this->belongsToMany(Fournisseur::class,'stock-produit')->withPivot('qte','prix');
    }

    public function client(){
        return $this->hasOne(Client::class);
    }
    public function DemandeCompteClientATerme(){
        return $this->hasOne(DemandeCompteClientATerme::class);
    }

    public function type_user(){
        return $this->belongsTo(TypeUser::class)->withDefault(['nom'=>'']);
    }

    public function ville(){
        return $this->belongsTo(Ville::class)->withDefault(['nom'=>'']);
    }




}
