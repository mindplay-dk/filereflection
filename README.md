mindplay/filereflection
=======================

#### ⚠️ DEPRECATED ⚠️ ####

> Consider using [`Roave/BetterReflection`](https://github.com/Roave/BetterReflection) instead.

https://github.com/mindplay-dk/filereflection

This library complements the PHP [reflection API](http://dk1.php.net/manual/en/book.reflection.php) with the missing ReflectionFile class.

[![Build Status](https://travis-ci.org/mindplay-dk/filereflection.png)](https://travis-ci.org/mindplay-dk/filereflection)

A few other libraries were available to do this already, but this one implements an
important feature missing from other implementations I could find: resolution of local
type-names according to the [name resolution rules](http://php.net/manual/en/language.namespaces.rules.php).

The interface is very simple:

    ReflectionFile {

        public __construct( string $path )

        public string getPath ( void )
        public string getNamespaceName ( void )
        public string resolveName ( string $name )
        public ReflectionClass getClass ( string $name )
        public ReflectionClass[] getClasses ( void )

    }

Usage of course is straight forward too:

    use mindplay\filereflection\ReflectionFile;

    $file = new ReflectionFile('/path/to/MyNamespace/MyClass.php');

    var_dump($file->resolveName('MyOtherClass')); // => '\MyNamespace\MyOtherClass'

Note that this library currently omits reflection/enumeration of functions, constants, etc.
