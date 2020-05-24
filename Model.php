<?php
namespace Model;
use \PDO;
use \PDOException;
use \PDOStatement;
use \Exception;
require_once 'config.php';
abstract class Model{
    /*Start properties*/
    protected bool $displayErrors = true;
    protected string $error = '';
    protected bool $connected = false;
    protected ?PDO $conn = null;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected ?PDOStatement $statement = null;
    protected string $command = '';
    protected int $limit = 0;
    protected int $fetch_style = PDO::FETCH_OBJ;
    protected string $orderBy = '';
    protected string $whereCondition = '';
    protected array $executeOn = [];
    protected array $fillable = [];
    protected int $type;
    protected bool $status = false;
    protected int $rowCount = 0;
    protected string $columnsAsString = '';
    protected string $valuesAsString = '';
    protected array $joinData = [];
    protected array $columns = [];
    /*End properties*/
    /*Start const*/
    const TYPE_CREATED = 1;
    const TYPE_SELECTED = 2;
    /*End const*/
    /*Start getters*/
    public function getError():string{
        return $this->error;
    }
    public function getStatus():bool{
        return $this->status;
    }
    public function isConnected():bool{
        return $this->connected;
    }
    /*End getters*/
    /*Start connection methods*/
    public function __construct(){
        $this->setTableName();
        $this->type = self::TYPE_CREATED;
        $this->connect();
    }
    protected function setTableName():string{
        return $this->table = empty($this->table) ? strtolower(get_class($this)).'s': $this->table;
    }
    public function connect():void{
        try{
            $mysqli = new PDO(dsn, username, password, options);
            $mysqli->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn = $mysqli;
            $this->connected = true;
        }catch(PDOException $e){
            $this->error = "Failed to connect to the database<br />". $e->getMessage();
            if($this->displayErrors):
                echo $this->error;
            endif;
            die();
        }
    }
    /*End connection methods*/
    /*Start static create method*/
    static public function create(array $arr):self{
        $model = new static();
        foreach($model->fillable as $columnName):
            $model->{$columnName} = $arr[$columnName];
        endforeach;
        return $model->save();
    }
    /*End static create method*/
    /*Start static selecting methods*/
    static public function find($val):self{
        $model = new static();
        return $model->find_protected($val);
    }
    static public function findOrFail($val):self{
        $model = new static();
        return $model->findOrFail_protected($val);
    }
    static public function select():self{
        return new static();
    }
    static public function querySelect(string $sqlStmt, array $data = []):array{
        $model = new static();
        $model->executeOn = $data;
        $model->query($sqlStmt)->exe();
        $re = [];
        if($model->status):
            $model->rowCount();
            $re = $model->fetchAll();
        endif;
        return $re;
    }
    static public function lastOne():self{
        $model = new static();
        return $model->lastOne_protected();
    }
    static public function firstOne():self{
        $model = new static();
        return $model->firstOne_protected();
    }
    static public function all():array{
        $model = new static();
        return $model->all_protected();
    }
    /*End static selecting methods*/
    /*Start public selecting methods (options)*/
    public function where(string $column, $a = null, $b = null):self{//where $column = 1; as default
        if($b === null):
            if($a === null):
                $a = 1;
            endif;
            $b = $a;
            $a = '=';
        endif;
        $this->executeOn[] = $b;
        if(strpos($this->whereCondition,'where') > -1):
            $this->whereCondition .= " `$column` $a ?";//additional condition
        else:
            $this->whereCondition = "where `$column` $a ?";//first condition
        endif;
        return $this;
    }
    public function whereNot($column, $val = 1):self{//where $column != 1 as Default
        return $this->where($column, '!=', $val);
    }
    public function whereNotNull($column):self{
        return $this->whereNot($column, 'null');
    }
    public function and():self{
        $this->whereCondition .= " &&";
        return $this;
    }
    public function andW(string $column, $a = null, $b = null){
        return $this->and()->where($column, $a, $b);
    }
    public function or():self{
        $this->whereCondition .= ' ||';
        return $this;
    }
    public function orW(string $column, $a = null, $b = null):self{
        return $this->or()->where($column, $a, $b);
    }
    public function openParW():self{
        $this->whereCondition .= ' (';
        return $this;
    }
    public function closeParW():self{
        $this->whereCondition .= ' )';
        return $this;
    }
    public function orderBy(string $column, string $type = 'DESC'):self{
        $type = strtoupper($type);
        $this->orderBy = "order by `$column` $type";
        return $this;
    }
    public function take(int $limit):self{
        $this->limit = $limit;
        return $this;
    }
    public function last():self{
        return $this->take(1)->orderBy($this->primaryKey, 'desc');
    }
    public function first():self{
        return $this->take(1)->orderBy($this->primaryKey, 'asc');
    }
    /**
     * @return $this|array
     */
    public function get(){
        $this->select_protected();
        //I store limit in a var because it'll be removed while executing prepare()
        $limit = $this->limit;
        $this->prepare()->exe();
        $re = [];
        if($this->status):
            $this->rowCount();
            $re = $limit === 1 ? $this->fetch(): $this->fetchAll();
        endif;
        return $re;
    }
    public function with(array $selectWithArr):self{
        if(!empty($selectWithArr)):
            foreach($selectWithArr as $objectName):
                $this->{$objectName} = $this->{$objectName}();
                if($this->{$objectName}->type === self::TYPE_CREATED):
                    $this->{$objectName} = $this->{$objectName}->get();
                endif;
            endforeach;
        endif;
        return $this;
    }
    /*End public selecting methods (options)*/
    /*Start protected selecting methods (Used in the statics methods)*/
    protected function all_protected():array{
        return $this->select_protected()->get();
    }
    protected function select_protected():self{
        $this->command = 'select * from';
        return $this;
    }
    protected function find_protected($val):self{
        return $this->select_protected()->where($this->primaryKey, $val)->take(1)->get();
    }
    protected function findOrFail_protected($val):self{
        $re = $this->find_protected($val);
        if($re !== null):
            return $re;
        else:
            die('404 error');
        endif;
    }
    protected function lastOne_protected():self{
        return $this->last()->get();
    }
    protected function firstOne_protected():self{
        return $this->first()->get();
    }
    /*End protected selecting methods (Used in the statics methods)*/
    /*Start save method*/
    public function save():?self{
        if($this->type === self::TYPE_CREATED)://insert
            $this->prepareDataToInsert();
            $query = "insert into `$this->table` ($this->columnsAsString) values ($this->valuesAsString)";
            $execution = $this->query($query)->exe();
            if($execution):
                $this->type = self::TYPE_SELECTED;
                $this->afterCreation();
                return $this->find_protected($this->conn->lastInsertId());
            endif;
        elseif($this->type === self::TYPE_SELECTED)://update
            if($this->isEdited()):
                $this->prepareDataToUpdate();
                $query = "update `$this->table` set $this->columnsAsString where $this->primaryKey = ?";
                $this->query($query)->exe();
                return $this;
            else:
                $this->error = 'Data was not changed, so there is no need to update it.';
            endif;
        endif;
        return null;//if it gets till here it must be false
    }
    /*End save method*/
    /*Start delete method*/
    public function del_protected():self{
        $this->command = 'delete from';
        return $this;
    }
    public function confirmDel():int{
        $this->prepare()->exe();
        $this->rowCount();
        return $this->rowCount;
    }
    public static function del():self{
        $model = new static();
        return $model->del_protected();
    }
    public function delete():?self{
        $re = null;
        if($this->type === self::TYPE_SELECTED):
            $this->command = 'delete from';
            $this->where($this->primaryKey, $this->{$this->primaryKey})->take(1);
            $this->prepare()->exe();
            if($this->status):
                $re = $this;
            endif;
        else:
            $this->error = 'Data is not inserted in the database yet to be deleted !';
        endif;
        return $re;
    }
    /*End delete method*/
    /*Update method*/
    public static function update():self{
        $model = new static();
        return $model->update_protected();
    }
    public function confirmUpdate(array $data):int{
        $this->prepareGeneralUpdate($data);
        $limit = $this->limit > 0 ? "limit $this->limit": '';
        $sqlStmt = "$this->command $this->columnsAsString $this->whereCondition $limit";
        $this->query($sqlStmt);
        $this->clearArguments();
        $this->exe();
        return $this->rowCount();
    }
    protected function update_protected():self{
        $this->command = "update $this->table set";
        return $this;
    }
    protected function prepareGeneralUpdate(array $data):self{
        $i = 0;
        $arr = $this->executeOn;
        $this->executeOn = [];
        foreach($data as $key => $value):
            $this->columnsAsString .= $i === 0 ? "`$key` = ?": ", `$key` = ?";
            $this->executeOn[] = $value;
            $i++;
        endforeach;
        $this->executeOn = array_merge($this->executeOn, $arr);
        return $this;
    }
    /*End update method*/
    /*abstract method*/
    abstract protected function afterCreation():void;

    /*Start protected methods*/
    protected function isEdited():bool{
        $old = $this->find_protected($this->{$this->primaryKey});
        $re = false;
        foreach($this->fillable as $column):
            if($this->{$column} !== $old->{$column}):
                $re = true;
                break;
            endif;
        endforeach;
        return $re;
    }
    protected function prepareDataToInsert():self{
        if(sizeof($this->fillable) > 0):
            foreach($this->fillable as $key => $column):
                $this->columnsAsString .= $key === 0 ? "`$column`": ", `$column`";
                $this->valuesAsString .= $key === 0 ? "?": ", ?";
                $this->executeOn[] = $this->{$column};
            endforeach;
        endif;
        return $this;
    }
    protected function prepareDataToUpdate():self{
        if(sizeof($this->fillable) > 0):
            foreach($this->fillable as $key => $column):
                $this->columnsAsString .= $key === 0 ? "`$column` = ?": ", `$column` = ?";
                $this->executeOn[] = $this->{$column};
            endforeach;
            $this->executeOn[] = $this->{$this->primaryKey};
        endif;
        return $this;
    }
    protected function prepare():self{
        $limit = $this->limit > 0 ? "limit $this->limit": '';
        $commandFrom = !empty($this->joinData)? $this->prepareJoin() : "$this->command `$this->table`";
        $sqlStmt = "$commandFrom $this->whereCondition $this->orderBy $limit";
        $this->query($sqlStmt);
        $this->clearArguments();
        return $this;
    }
    public function innerJoin($selectWithArr):self{
        //not selected (query not executed yet)!
        foreach ($selectWithArr as $selectWith):
            $funcName = "{$selectWith}Join";
            $this->joinData[] = $this->{$funcName}();
        endforeach;
        return $this;
    }
    protected function prepareJoin():string{
        $command = "select `{$this->table}`.*";
        $from = " from `{$this->table}`";
        foreach ($this->joinData as $modelName => $data):
            if($data['columns'] === '*'):
                $command .= ", `{$data['table']}`.*";
            else:
                foreach ($data['columns'] as $column):
                    $command .= ", `{$data['table']}`.$column";
                endforeach;
            endif;
            $from .= " inner join `{$data['table']}` on `{$this->table}`.`{$data['firstColumn']}` = `{$data['table']}`.`{$data['secondColumn']}`";
        endforeach;
        $this->command = $command;
        return $command.$from;
    }
    protected function query($sqlStmt):self{
        $this->statement = $this->conn->prepare($sqlStmt);
        return $this;
    }
    protected function exe():bool{
        try{
            $this->status = $this->statement->execute($this->executeOn);
        }catch(PDOException $e){
            $this->error = $e->getMessage().'<br />Query: '.$this->statement->queryString.'<br />';
            if($this->displayErrors):
                echo $this->error;
            endif;
        }
        $this->executeOn = [];
        return $this->status;
    }
    protected function fetch():?self{
        $row = $this->statement->fetch($this->fetch_style);
        $model = null;
        if($this->rowCount):
            $model = clone $this;
            $model->type = self::TYPE_SELECTED;
            if(!empty($this->joinData)):
                $model = $this->rowJoinToObj($row);
            else:
                $model = $this->rowToObj($row);
            endif;
            $this->joinData = [];
        endif;
        return $model;
    }
    protected function fetchAll():array{
        $rows = $this->statement->fetchAll($this->fetch_style);
        $data = [];
        if($this->rowCount):
            foreach($rows as $row):
                if(!empty($this->joinData)):
                    $model = $this->rowJoinToObj($row);
                else:
                    $model = $this->rowToObj($row);
                endif;
                $data[] = $model;
            endforeach;
            $this->joinData = [];
        endif;
        return $data;
    }
    protected function rowToObj($row):self{
        $model = clone $this;
        $model->type = self::TYPE_SELECTED;
        foreach ($row as $key => $column):
            $model->{$key} = $column;
        endforeach;
        return $model;
    }
    protected function rowJoinToObj($row):self{
        $model = clone $this;
        $model->type = self::TYPE_SELECTED;
        foreach ($model->columns as $column):
            $model->{$column} = $row->{$column};
        endforeach;
        foreach ($this->joinData as $item):
            $modelName = $item['modelName'];
            $obj = new $modelName();
            $columns = $item['columns'] === '*' ? $obj->columns: $item['columns'];
            foreach($columns as $column):
                $obj->{$column} = $row->{$column};
            endforeach;
            $obj->type = self::TYPE_SELECTED;
            $model->{$item['withName']} = $obj;
        endforeach;
        return $model;
    }
    protected function rowCount():int{
        return $this->rowCount = $this->statement->rowCount();
    }
    protected function clearArguments():void{
        $this->command = '';
        $this->limit = 0;
        $this->orderBy = '';
        $this->whereCondition = '';
        $this->columnsAsString = '';
        $this->valuesAsString = '';
    }
    /*Start relationships methods*/
    protected function belongsTo($class, $foreignKey):?Model{
        if($this->requireClass($class)):
            $model = new $class();
            return $model->find_protected($this->{$foreignKey});
        endif;
        return null;
    }
    protected function hasMany($class, $foreignKey):?Model{
        if($this->requireClass($class)):
            $model = new $class();
            return $model->where($foreignKey, $this->{$this->primaryKey});
        endif;
        return null;
    }
    protected function hasOne(string $class, $foreignKey = null):?Model{
        if($this->requireClass($class)):
            $model = new $class();
            $foreignKeyWhere = $foreignKey === null ? $model->primaryKey: $foreignKey;
            return $model->where($foreignKeyWhere, $this->{$this->primaryKey})->take(1)->get();
        endif;
        return null;
    }
    /*End relationships methods*/
    protected function requireClass($class):bool{
        $file = "$class.php";
        $boolean = false;
        try{
            if(!file_exists($file)):
                throw new Exception("$file doesn't exist");
            else:
                require_once $file;
                $boolean = true;
            endif;
        }catch(Exception $e){
            $this->error = $e->getMessage();
            if($this->displayErrors):
                echo $this->error;
            endif;
        }
        return $boolean;
    }
    /*End protected methods*/
    /*count*/
    public static function count():self{
        $model = new static();
        return $model->count_protected();
    }
    protected function count_protected():self{
        $this->command = 'select count(*) from';
        $this->limit = 1;
        return $this;
    }
    public function getCount():int{
        $this->prepare()->exe();
        $count = 0;
        if($this->status)
            $count = $this->statement->fetch(PDO::FETCH_ASSOC)['count(*)'];
        return $count;
    }
}