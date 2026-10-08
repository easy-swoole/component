<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2019-01-06
 * Time: 22:58
 */

namespace EasySwoole\Component\Context;


use EasySwoole\Component\Singleton;
use Swoole\Coroutine;

class ContextManager
{
    use Singleton;

    private array $context = [];

    private array $deferList = [];


    public function set($key,$value,int|null $cid = null):ContextManager
    {
        if($cid == null){
            $cid = $this->getCid();
        }
        $this->context[$cid][$key] = $value;
        return $this;
    }

    public function get($key,int|null $cid = null):mixed
    {
        if($cid == null){
            $cid = $this->getCid();
        }
        if(isset($this->context[$cid][$key])){
            return $this->context[$cid][$key];
        }
        return null;
    }

    public function unset($key,int|null $cid = null):bool
    {
        if($cid == null){
            $cid = $this->getCid();
        }
        if(isset($this->context[$cid][$key])){
            unset($this->context[$cid][$key]);
            return true;
        }else{
            return false;
        }
    }

    public function destroy(int|null $cid = null):void
    {
        if($cid == null){
            $cid = $this->getCid();
        }
        unset($this->context[$cid]);
    }

    protected function getCid(int|null $cid = null):int
    {
        if($cid !== null){
            return $cid;
        }
        $cid = Coroutine::getUid();
        if(!isset($this->deferList[$cid])){
            $this->deferList[$cid] = true;
            Coroutine::defer(function ()use($cid){
                unset($this->deferList[$cid]);
                $this->destroy($cid);
            });
        }
        return $cid;
    }

    public function destroyAll():void
    {
        $this->context = [];
    }

    public function getContextArray(int|null $cid = null):?array
    {
        $cid = $this->getCid($cid);
        return $this->context[$cid] ?? null;
    }
}