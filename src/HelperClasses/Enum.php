<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 13:38
 *
 * Implements the abstract base for all enum types
 *
 * example of a typical enum:
 *
 *    class DayOfWeek extends Enum
 *    {
 *        const Sunday    = 0;
 *        const Monday    = 1;
 *        const Tuesday   = 2;
 *        const Wednesday = 3;
 *        const Thursday  = 4;
 *        const Friday    = 5;
 *        const Saturday  = 6;
 *    }
 *
 * usage examples:
 *
 *     $monday = Enum::fromString( 'DayOfWeek::Monday' );           // (int) 1
 *     $monday = DayOfWeek::Monday                                  // (int) 1
 *     $monday = Enum::toString( 'DayOfWeek', DayOfWeek::Monday );  // (string) "DayOfWeek::Monday"
 *     $monday = Enum::label( 'DayOfWeek', DayOfWeek::Monday );     // (string) "Monday"
 *
 **/

namespace FlyCubePHP\HelperClasses;

abstract class Enum
{
    // make sure there are never any instances created
    /**
     * Enum constructor.
     * @throws \Exception Enum and Subclasses cannot be instantiated.
     */
    final private function __construct() {
        throw new \Exception('Enum and Subclasses cannot be instantiated.');
    }

    /**
     * For a given $enumType, give the complete string representation for the given $enumValue (class::const)
     *
     * @param string $enumType - enum class
     * @param integer $enumValue - enum value
     * @throws \ReflectionException if the class does not exist.
     * @return string
     */
    final public static function toString(string $enumType, int $enumValue) {
        $result = 'NotAnEnum::IllegalValue';
        if (class_exists($enumType, false)) {
            $reflector = new \ReflectionClass($enumType);
            $result = $reflector->getName() . '::IllegalValue';
            foreach ($reflector->getConstants() as $key => $val) {
                if ($val == $enumValue) {
                    $result = str_replace('IllegalValue', $key, $result);
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Give the integer associated with the const of the given string in the format of "class:const"
     *
     * @param string $string - enum string value
     * @throws \Exception Enum::FromString( $string ) Input string is not in the expected format.
     * @throws \Exception VALUE does not map to an Enum field
     * @throws \ReflectionException if the class does not exist.
     * @return integer
     */
    final public static function fromString(string $string) {
        if (strpos($string, '::') < 1)
            throw new \Exception('Enum::fromString( $string ) Input string is not in the expected format.');

        list($class, $const) = explode('::', $string);
        if (class_exists($class, false)) {
            $reflector = new \ReflectionClass($class);
            if ($reflector->isSubClassOf('\FlyCubePHP\HelperClasses\Enum')) {
                if ($reflector->hasConstant($const))
                    return eval(sprintf('return %s;', $string));
            }
        }
        throw new \Exception(sprintf('%s does not map to an Enum field', $string));
    }

    /**
     * For a given $enumType, give the label associated with the given $enumValue (const name in class definition)
     *
     * @param string $enumType - enum class
     * @param integer $enumValue - enum value
     * @throws \ReflectionException if the class does not exist.
     * @return string
     */
    final public static function label(string $enumType, int $enumValue) {
        $result = 'IllegalValue';
        if (class_exists( $enumType, false)) {
            $reflector = new \ReflectionClass($enumType);
            foreach ($reflector->getConstants() as $key => $val) {
                if ($val == $enumValue) {
                    $result = $key;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * @param string $enumType - enum class
     * @param integer $enumValue - some value
     * @return bool
     * @throws \ReflectionException
     */
    final public static function isValidValue(string $enumType, int $enumValue) {
        if (class_exists($enumType)) {
            $reflector = new \ReflectionClass($enumType);
            if ($reflector->isSubClassOf('FlyCubePHP\HelperClasses\Enum')) {
                foreach ($reflector->getConstants() as $label => $value) {
                    if ($value == $enumValue)
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string $enumType - enum class
     * @param string $enumValue - some string value
     * @return bool
     * @throws \ReflectionException
     */
    final public static function isValidLabel(string $enumType, string $enumValue) {
        if (class_exists($enumType)) {
            $reflector = new \ReflectionClass($enumType);
            if ($reflector->isSubClassOf('FlyCubePHP\HelperClasses\Enum')) {
                foreach ($reflector->getConstants() as $label => $value) {
                    if ($label == $enumValue
                        || strtolower($label) == $enumValue)
                        return true;
                }
            }
        }
        return false;
    }
}
