<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'id_administrator', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = ['password', 'api_token', 'remember_token',];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = ['email_verified_at' => 'datetime',];


    public function company()
    {
        return $this->hasOne(Company::class);
    }

    public function validate_mail_server(){
        if(!is_null($this->mail_host) && !is_null($this->mail_port) && !is_null($this->mail_username) && !is_null($this->mail_password) && !is_null($this->mail_encryption))
          if($this->mail_host != '' && $this->mail_port != '' && $this->mail_username != '' && $this->mail_password != '' && $this->mail_encryption != '')
            return true;
          else
            return false;
        else
          return false;
    }
}
