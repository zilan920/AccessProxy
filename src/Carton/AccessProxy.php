<?php
namespace Carton;

use Carton\exceptions\InvalidPathException;

class AccessProxy
{
    private $_instance;

    private $_className;

    private $_classArgs = [];

    private $_classPath = '';

    protected $_commonHooks;
    protected $_specifiedHooks;

    CONST BEFORE_CALL = 'call.before';
    CONST AFTER_CALL  = 'call.after';
    CONST DESTRUCT_CALL = 'call.destruct';
    CONST CONSTRUCT_CALL = 'call.destruct';

    CONST ACCESSIBLE_PROPERTIES = ['_className', '_classPath'];

    CONST ILLEGAL_PROPERTIES = ['_instance'];

    CONST AVAILABLE_HOOKS = [
        self::BEFORE_CALL,
        self::AFTER_CALL,
        self::DESTRUCT_CALL,
        self::CONSTRUCT_CALL,
    ];

    public function __destruct()
    {
        $this->_applyHook(self::DESTRUCT_CALL);
    }

    public function __construct($className, $classArgs, $classPath = '')
    {
        $this->_setClassName($className);
        $this->_setClassArgs($classArgs);
        !empty($classPath) && $this->_setclassPath($classPath);
        $this->_applyHook(self::CONSTRUCT_CALL);
    }

    public function _getInstance()
    {
        if(null === $this->_instance) {
            $this->_instance = $this->_initInstance();
        }
        return $this->_instance;
    }

    public function __call($name, $arguments)
    {
        $this->_applyHook(self::BEFORE_CALL, $name);
        $instance = $this->_getInstance();
        $r = call_user_func_array(
            array($instance, $name),
            $arguments
        );
        $this->_applyHook(self::AFTER_CALL, $name);
        return $r;
    }

    public function _hook($name, $hook, $condition = '')
    {
        if (in_array($name, self::AVAILABLE_HOOKS)) {
            if (!empty($condition)) {
                $this->_specifiedHooks[$name] = $hook;
            } else {
                $this->_commonHooks[$name] = $hook;
            }
        }
    }

    protected function _applyHook($name, $condition = '')
    {
        $hooks = empty($condition) ? $this->_commonHooks : $this->_specifiedHooks;
        if (isset($hooks[$name])) {
            $hook = $hooks[$name];
            $instance = $this->_getInstance();
            if (is_callable($hook) || function_exists($hook)) {
                return call_user_func($hook);
                //成员函数可使用method_exists检测，静态函数可使用property_exists检测
            } else if (method_exists($instance, $hook) || property_exists($instance, $hook)) {
                return call_user_func([$instance, $hook]);
            }
        }
    }

    protected function _setClassName($className)
    {
        !is_null($className) && ($this->_className = $className);
    }

    protected function _setClassPath($classPath)
    {
        !is_null($classPath) && ($this->_classPath = $classPath);
    }

    protected function _setClassArgs($classArgs)
    {
        !is_null($classArgs) && ($this->_classArgs = $classArgs);
    }

    private function _initInstance()
    {
        if (empty($this->_className)) {
            return null;
        }
        if (!empty($this->_classPath)) {
            try {
                if (!include_once($this->_classPath)) {
                    throw new InvalidPathException($this->_classPath . 'Not Exists');
                }
            } catch (InvalidPathException $e) {
                //todo add log
            }
        }
        $class_name = $this->_className;
        return new $class_name($this->_classArgs);
    }

    public function __get($name)
    {
        if (in_array($name, self::ACCESSIBLE_PROPERTIES)) {
            return  $this->$name;
        } elseif (in_array($name, self::ILLEGAL_PROPERTIES)) {
            //todo add log
            return null;
        } else {
            return $this->_getInstance()->$name;
        }
    }

    public function __isset($name)
    {
        return isset($this->_getInstance()->$name);
    }

    public function __unset($name)
    {
        unset($this->_getInstance()->$name);
    }
}