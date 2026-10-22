<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018-12-27
 * Time: 15:54
 */

namespace EasySwoole\Component;


use Swoole\Table;

class TableManager
{
    use Singleton;

    private array $list = [];


    /**
     * @param $name
     * @param array $columns    ['col'=>['type'=>Table::TYPE_STRING,'size'=>1]]
     * @param int $size
     */
    public function add(int|string $name,array $columns,int $size = 1024):void
    {
        if(!isset($this->list[$name])){
            $table = new Table($size);
            foreach ($columns as $column => $item){
                $table->column($column,$item['type'],$item['size']);
            }
            $table->create();
            $this->list[$name] = $table;
        }
    }

    public function get(int|string $name):?Table
    {
        if(isset($this->list[$name])){
            return $this->list[$name];
        }else{
            return null;
        }
    }

    public function del(int|string $name):void
    {
        if(isset($this->list[$name])){
            $this->list[$name]->destroy();
            unset($this->list[$name]);
        }
    }
}
