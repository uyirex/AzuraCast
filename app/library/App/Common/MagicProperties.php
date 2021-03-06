<?php
namespace App\Common;

/**
 * A common class that implements magic getter/setter and ArrayAccess on properties.
 * Any other class can extend this to gain its functionality.
 *
 * Allows otherwise protected properties to be accessed as objects or arrays:
 *  - $obj['property']
 *  - $obj->property
 *
 * If defined, camel-cased getter/setter functions will override:
 *  - $obj->property; -> $obj->getProperty()
 *  - $obj->property = 'blah'; -> $obj->setProperty('blah');
 *
 * @package App\Common
 */
abstract class MagicProperties implements \ArrayAccess
{
    /**
     * Magic methods.
     */

    public function __get($key)
    {
        $method_name = $this->_getMethodName($key, 'get');

        if (method_exists($this, $method_name))
            return $this->$method_name();
        else
            return $this->_getVar($key);
    }

    public function __set($key, $value)
    {
        $method_name = $this->_getMethodName($key, 'set');

        if (method_exists($this, $method_name))
            return $this->$method_name($value);
        else
            return $this->_setVar($key, $value);
    }

    public function __call($method, $arguments)
    {
        if (substr($method, 0, 3) == "get") {
            $var = $this->_getVarName(substr($method, 3));
            return $this->_getVar($var);
        }
        else if (substr($method, 0, 3) == "set") {
            $var = $this->_getVarName(substr($method, 3));
            $this->_setVar($var, $arguments[0]);
            return $this;
        }
    }

    protected function _getVar($var)
    {
        if (property_exists($this, $var))
            return $this->$var;
        else
            return NULL;
    }
    protected function _setVar($var, $value)
    {
        if (property_exists($this, $var))
            $this->$var = $value;

        return $this;
    }

    // Converts "varNameBlah" to "var_name_blah".
    protected function _getVarName($var)
    {
        return strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1', $var));
    }

    // Converts "getvar_name_blah" to "getVarNameBlah".
    protected function _getMethodName($var, $prefix = '')
    {
        return $prefix.str_replace(" ", "", ucwords(strtr($var, "_-", "  ")));
    }

    /**
     * ArrayAccess implementation
     */

    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }
    public function offsetSet($key, $value)
    {
        $method_name = $this->_getMethodName($key, 'set');

        if (method_exists($this, $method_name))
            return $this->$method_name($value);
        else
            return $this->_setVar($key, $value);
    }
    public function offsetGet($key)
    {
        $method_name = $this->_getMethodName($key, 'get');
        if (method_exists($this, $method_name))
            return $this->$method_name();
        else
            return $this->_getVar($key);
    }
    public function offsetUnset($offset)
    {
        if (property_exists($this, $offset))
            unset($this->$offset);
    }
}