<?php
/**
 * Created by PhpStorm.
 * User: fangzihan
 * Date: 14/02/2018
 * Time: 7:11 PM
 */
namespace Carton;

use Carton\exceptions\InvalidPathException;

class AccessProxy
{
    private $instance;

    private $className;

    private $classArgs = [];

    private $classPath = '';

    protected $hooks;
    protected $commonHooks;
    protected $specifiedHooks;

    CONST BEFORE_CALL = 'call.before';
    CONST AFTER_CALL  = 'call.after';
    CONST DESTRUCT_CALL = 'call.destruct';
    CONST CONSTRUCT_CALL = 'call.destruct';

    CONST ACCESSIBLE_PROPERTIES = ['className', 'classPath'];

    CONST ILLEGAL_PROPERTIES = ['instance'];

    CONST AVAILABLE_HOOKS = [
        self::BEFORE_CALL,
        self::AFTER_CALL,
        self::DESTRUCT_CALL,
        self::CONSTRUCT_CALL,
    ];

    public function __destruct()
    {
        $this->applyHook(self::DESTRUCT_CALL);
    }

    public function __construct($className, $classArgs, $classPath = '')
    {
        $this->setClassName($className);
        $this->setClassArgs($classArgs);
        !empty($classPath) && $this->setclassPath($classPath);
        $this->applyHook(self::CONSTRUCT_CALL);
    }

    public function getInstance()
    {
        if(null === $this->instance) {
            $this->instance = $this->initInstance();
        }
        return $this->instance;
    }

    public function __call($name, $arguments)
    {
        $this->applyHook(self::BEFORE_CALL, $name);
        $instance = $this->getInstance();
        $r = call_user_func_array(
            array($instance, $name),
            $arguments
        );
        $this->applyHook(self::AFTER_CALL, $name);
        return $r;
    }

    public function hook($name, $hook, $condition = '')
    {
        if (in_array($name, self::AVAILABLE_HOOKS)) {
            if (!empty($condition)) {
                $this->specifiedHooks[$name] = $hook;
            } else {
                $this->commonHooks[$name] = $hook;
            }
        }
    }

    protected function applyHook($name, $condition = '')
    {
        $hooks = empty($condition) ? $this->commonHooks : $this->specifiedHooks;
        if (isset($hooks[$name])) {
            $hook = $hooks[$name];
            $instance = $this->getInstance();
            if (is_callable($hook) || function_exists($hook)) {
                call_user_func($hook);
                //成员函数可使用method_exists检测，静态函数可使用property_exists检测
            } else if (method_exists($instance, $hook) || property_exists($instance, $hook)) {
                call_user_func([$instance, $hook]);
            }
        }
    }

    protected function setClassName($className)
    {
        !is_null($className) && ($this->className = $className);
    }

    protected function setClassPath($classPath)
    {
        !is_null($classPath) && ($this->classPath = $classPath);
    }

    protected function setClassArgs($classArgs)
    {
        !is_null($classArgs) && ($this->classArgs = $classArgs);
    }

    private function initInstance()
    {
        if (empty($this->className)) {
            return null;
        }
        if (!empty($this->classPath)) {
            try {
                if (!include_once($this->classPath)) {
                    throw new InvalidPathException($this->classPath . 'Not Exists');
                }
            } catch (Exception $e) {
                //todo add log
            }
        }
        $class_name = $this->className;
        return new $class_name($this->classArgs);
    }

    public function __get($name)
    {
        if (in_array($name, self::ACCESSIBLE_PROPERTIES)) {
            return  $this->$name;
        } elseif (in_array($name, self::ILLEGAL_PROPERTIES)) {
            //todo add log
            return null;
        } else {
            return $this->getInstance()->$name;
        }
    }

    public function __isset($name)
    {
        return isset($this->getInstance()->$name);
    }

    public function __unset($name)
    {
        unset($this->getInstance()->$name);
    }
}