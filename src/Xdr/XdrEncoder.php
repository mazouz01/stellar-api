<?php


namespace ZuluCrypto\StellarSdk\Xdr;

use ZuluCrypto\StellarSdk\Xdr\Iface\XdrEncodableInterface;


/**
 * See: https://tools.ietf.org/html/rfc4506
 *
 * - Data is stored in big endian
 */
class XdrEncoder
{
    /**
     * NOTE: do not access this directly, it's used for caching whether the current
     * platform is big endian.
     *
     * Instead, call self::nativeIsBigEndian()
     *
     * @deprecated use nativeIsBigEndian() instead
     * @var bool
     */
    private static $nativeIsBigEndian;

    /**
     * @param       $value
     * @param null  $expectedLength in bytes
     * @param false $padUnexpectedLength If true, an unexpected length is padded instead of throwing an exception
     * @return string
     */
    public static function opaqueFixed($value, $expectedLength = null, $padUnexpectedLength = false)
    {
        // Length greater than expected length is always an error
        if ($expectedLength && strlen($value) > $expectedLength) throw new \InvalidArgumentException(sprintf('Unexpected length for value. Has length %s, expected %s', strlen($value), $expectedLength));
        if ($expectedLength && !$padUnexpectedLength && strlen($value) != $expectedLength) throw new \InvalidArgumentException(sprintf('Unexpected length for value. Has length %s, expected %s', strlen($value), $expectedLength));

        if ($expectedLength && strlen($value) != $expectedLength) {
            $value = self::applyPadding($value, $expectedLength);
        }

        return self::applyPadding($value);
    }

    /**
     * Variable-length opaque data
     *
     * Maximum length is 2^32 - 1
     *
     * @param $value
     * @return string
     */
    public static function opaqueVariable($value)
    {
        $maxLength = pow(2, 32) - 1;
        if (strlen($value) > $maxLength) throw new \InvalidArgumentException(sprintf('Value of length %s is greater than the maximum allowed length of %s', strlen($value), $maxLength));

        $bytes = '';
        
        $bytes .= self::unsignedInteger(strlen($value));
        $bytes .= $value;

        return $bytes;
    }

    public static function signedInteger($value)
    {
        // pack() does not support a signed 32-byte int, so work around this with
        // custom encoding
        return (self::nativeIsBigEndian()) ? pack('l', $value) : strrev(pack('l', $value));
    }

    public static function unsignedInteger($value)
    {
        // unsigned 32-bit big-endian
        return pack('N', $value);
    }

    public static function signedInteger64($value)
    {
        // pack() does not support a signed 64-byte int, so work around this with
        // custom encoding
        return (self::nativeIsBigEndian()) ? pack('q', $value) : strrev(pack('q', $value));
    }

    /**
     * Use this to write raw bytes representing a 64-bit integer
     *
     * This value will be padded up to 8 bytes
     *
     * @param $value
     * @return string
     */
    public static function integer64RawBytes($value)
    {
        // Some libraries will give a 4-byte value here but it must be encoded
        // as 8
        return self::applyPadding($value, 8, false);
    }

    public static function unsignedInteger64($value)
    {
        // unsigned 64-bit big-endian
        return pack('J', $value);
    }

    public static function signedHyper($value)
    {
        return self::signedInteger64($value);
    }

    public static function unsignedHyper($value)
    {
        return self::unsignedInteger64($value);
    }

    public static function unsignedInteger256($value)
    {
        return self::opaqueFixed($value, (256/8));
    }

    public static function boolean($value)
    {
        // Equivalent to 1 or 0 uint32
        return ($value) ? self::unsignedInteger(1) : self::unsignedInteger(0);
    }

    public static function string($value, $maximumLength = null)
    {
        if ($maximumLength === null) $maximumLength = pow(2, 32) - 1;

        if (strlen($value) > $maximumLength) throw new \InvalidArgumentException('string exceeds maximum length');

        $bytes = self::unsignedInteger(strlen($value));
        $bytes .= $value;

        return $bytes;
    }

    /**
     * Encodes an optional data value as XDR.
     *
     * Any non-null $value will be encoded and returned
     *
     * @param XdrEncodableInterface $value
     * @return string
     */
    public static function optional(XdrEncodableInterface $value = null)
    {
        $bytes = '';

        if ($value !== null) {
            $bytes .= self::boolean(true);
            $bytes .= $value->toXdr();
        }
        else {
            $bytes .= self::boolean(false);
        }

        return $bytes;
    }

    /**
     * Ensures $value's length is a multiple of $targetLength bytes
     *
     * The default value for XDR is 4
     *
     * @param $value
     * @param $targetLength - desired length after padding is applied
     * @param $rightPadding - pad on the right of the value, false to pad to the left
     * @return string
     */
    private static function applyPadding($value, $targetLength = 4, $rightPadding = true)
    {
        // No padding necessary if it's a multiple of 4 bytes
        if (strlen($value) % $targetLength === 0) return $value;

        $numPaddingChars = $targetLength - (strlen($value) % $targetLength);

        if ($rightPadding) {
            return $value . str_repeat(chr(0), $numPaddingChars);
        }
        else {
            return str_repeat(chr(0), $numPaddingChars) . $value;
        }
    }

    /**
     * @return bool
     */
    private static function nativeIsBigEndian()
    {
        if (null === self::$nativeIsBigEndian) {
            self::$nativeIsBigEndian = pack('L', 1) === pack('N', 1);
        }

        return self::$nativeIsBigEndian;
    }
}