<?php

namespace mindplay\filereflection;

use ReflectionClass;

/**
 * This class complements the PHP reflection API with the missing file reflector.
 */
class ReflectionFile
{
    const CHAR = - 1;
    const SCAN = 1;
    const CLASS_NAME = 2;
    const SKIP_CLASS = 3;
    const NAMESPACE_NAME = 4;
    const USE_CLAUSE = 5;
    const USE_CLAUSE_AS = 6;

    const SKIP = 7;
    const NAME = 8;
    const COPY_LINE = 9;
    const COPY_ARRAY = 10;

    /**
     * @var string absolute path of reflected file
     */
    protected $_path;

    /**
     * @var string fully-qualified namespace name (of the file)
     */
    protected $_namespace;

    /**
     * @var string[] hash map where fully-qualified class-name maps to a local (to the file) fully-qualified name-alias
     */
    protected $_uses = array();

    /**
     * @var string[] list of fully-qualified class names
     */
    protected $_classNames = array();

    /**
     * @var ReflectionClass[] list of class reflections
     */
    protected $_classes;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->_path = $path;

        $source = file_get_contents($path);

        $state = self::SCAN;
        $nesting = 0;
        $class = null;
        $namespace = '';
        $use = '';
        $use_as = '';

        $line = 0;

        foreach (token_get_all($source) as $token) {
            list($type, $str, $line) = is_array($token) ? $token : array(self::CHAR, $token, $line);

            switch ($state) {
                case self::SCAN:
                    if ($type == T_CLASS) {
                        $state = self::CLASS_NAME;
                    }
                    if ($type == T_NAMESPACE) {
                        $state = self::NAMESPACE_NAME;
                        $namespace = '';
                    }
                    if ($type === T_USE && $nesting === 0) {
                        $state = self::USE_CLAUSE;
                        $use = '';
                    }
                    break;

                case self::NAMESPACE_NAME:
                    if ($type == T_STRING || $type == T_NS_SEPARATOR) {
                        $namespace .= $str;
                    } else {
                        if ($str == ';') {
                            $this->_namespace = $namespace;
                            $state = self::SCAN;
                        }
                    }
                    break;

                case self::USE_CLAUSE:
                    if ($type == T_AS) {
                        $use_as = '';
                        $state = self::USE_CLAUSE_AS;
                    } elseif ($type == T_STRING || $type == T_NS_SEPARATOR) {
                        $use .= $str;
                    } elseif ($type === self::CHAR) {
                        if ($str === ',' || $str === ';') {
                            $this->_uses[substr($use, 1 + strrpos($use, '\\'))] = $use;

                            if ($str === ',') {
                                $state = self::USE_CLAUSE;
                                $use = '';
                            } elseif ($str === ';') {
                                $state = self::SCAN;
                            }
                        }
                    }
                    break;

                case self::USE_CLAUSE_AS:
                    if ($type === T_STRING || $type === T_NS_SEPARATOR) {
                        $use_as .= $str;
                    } elseif ($type === self::CHAR) {
                        if ($str === ',' || $str === ';') {
                            $this->_uses[$use_as] = $use;

                            if ($str === ',') {
                                $state = self::USE_CLAUSE;
                                $use = '';
                            } elseif ($str === ';') {
                                $state = self::SCAN;
                            }
                        }
                    }
                    break;

                case self::CLASS_NAME:
                    if ($type == T_STRING) {
                        $this->_classNames[] = ($namespace ? $namespace . '\\' : '') . $str;
                        $state = self::SKIP_CLASS;
                    }
                    break;
            }

            if (($state >= self::SKIP_CLASS) && ($type == self::CHAR)) {
                switch ($str) {
                    case '{':
                        $nesting++;
                        break;

                    case '}':
                        $nesting--;
                        if ($nesting == 0) {
                            $class = null;
                            $state = self::SCAN;
                        }
                        break;
                }
            }

            if ($type == T_CURLY_OPEN) {
                $nesting++;
            }
        }
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @return string
     */
    public function getNamespaceName()
    {
        return $this->_namespace;
    }

    /**
     * @return ReflectionClass[] all classes defined in the reflected file
     */
    public function getClasses()
    {
        if ($this->_classes === null) {
            $this->_classes = array();

            foreach ($this->_classNames as $name) {
                $this->_classes[] = new ReflectionClass($name);
            }
        }

        return $this->_classes;
    }

    /**
     * @param string $name unqualified (or fully-qualified) class-name
     *
     * @return ReflectionClass
     */
    public function getClass($name)
    {
        $name = substr($this->resolveName($name), 1);

        return new ReflectionClass($name);
    }

    /**
     * @param string $name unqualified (or fully-qualified) type-name
     *
     * @return string fully-qualified type-name (with a leading backslash)
     */
    public function resolveName($name)
    {
        $qualified = substr_compare($name, '\\', 0, 1) === 0;

        if ($qualified) {
            $name = substr($name, 1); // remove leading backslash
        }

        if (isset($this->_uses[$name])) {
            $name = $this->_uses[$name];
        } elseif ($this->_namespace !== null && $qualified === false) {
            $name = $this->_namespace . '\\' . $name;
        }

        return '\\' . $name;
    }
}
