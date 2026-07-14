<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeReset extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'email',
        'user_id',
        'type_code',
        'expiration_date',
        'utilise',
    ];

    public static function lireSurUser($user_id, $type_code, $utilise)
    {
        $obj = CodeReset::orderBy('id', 'desc')
            ->where('type_code', $type_code)
            ->where('utilise', $utilise)
            ->where('user_id', $user_id)
            ->whereDate('created_at', date('Y-m-d'))
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new CodeReset();
    }

    public static function lireSurEmail($email)
    {
        $obj = CodeReset::orderBy('id', 'desc')->where('email', $email)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new CodeReset();
    }
}
