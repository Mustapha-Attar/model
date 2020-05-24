<?php
require_once 'Model.php';
use Model\Model;
class Profile extends Model{
    protected string $primaryKey = 'profileID';
    protected array $fillable = ['profileID', 'birthDate', 'img', 'bio'];
    protected bool $displayErrors = true;
    public function __construct(){
        parent::__construct();
        $this->columns = $this->fillable;
    }
    public function user(){
        return $this->belongsTo('User', 'profileID');
    }
    protected function afterCreation():void{
    }
}