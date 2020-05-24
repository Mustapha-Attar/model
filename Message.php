<?php
require_once 'Model.php';
require_once 'User.php';
use Model\Model;
class Message extends Model{
    protected string $table = 'msgs';
    protected bool $displayErrors = true;
    protected string $primaryKey = 'msgID';
    protected array $fillable = ['msg', 'userID', 'isRead'];
    public int $isRead = 0;//to take a default value when inserting a new row to the database
    public function __construct(){
        parent::__construct();
        $this->columns = $this->fillable;
        $columns[] = 'msgID';
        $columns[] = 'timeStamp';
    }
    public function user():?User{
        return $this->belongsTo('User', 'userID');
    }
    public function userJoin():array{
        return ["table"=> 'users', "modelName" => 'User',
            "firstColumn" => 'userID', "secondColumn" => 'id',
            "withName" => 'user'];
    }
    public function profileJoin():array{
        return ["table"=> 'profiles', "modelName" => 'Profile',
            "firstColumn" => 'userID', "secondColumn" => 'profileID',
            "withName" => 'profile'];
    }
    public function profile():?Profile{
        $this->user = $this->user ?? $this->user();
        return $this->user->profile();
    }
    protected function afterCreation(): void{}
}