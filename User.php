<?php
require_once 'Model.php';
require_once 'Profile.php';
use Model\Model;
class User extends Model{
    protected array $fillable = ['name', 'email'];
    protected bool $displayErrors = true;
    protected array $selectWith = ['profile'];
    static public bool $autoComplete = false;
    public function __construct(){
        parent::__construct();
        $this->columns = $this->fillable;
        $this->columns[] = 'id';
        $this->columns[] = 'created_at';
    }
    public function messages(){
        return $this->hasMany('Message', 'userID')->orderBy('msgID')->take(2);
    }
    public function messagesJoin():array{
        return ["table"=> 'msgs', "modelName" => 'Message',
            "firstColumn" => 'id', "secondColumn" => 'userID',
            "withName" => 'message', "columns" => ['msgID']];
    }
    public function profileJoin():array{
        return ["table"=> 'profiles', "modelName" => 'Profile',
            "firstColumn" => 'id', "secondColumn" => 'profileID',
            "withName" => 'profile', "columns" => '*'];
    }
    public function profile():?Profile{
        return $this->hasOne('Profile', 'profileID');
    }
    protected function afterCreation():void{
        profile::create([
            "profileID" => $this->conn->lastInsertId(),
            "birthDate" => null,
            "img" => null,
            "bio" => null
        ]);
    }
}