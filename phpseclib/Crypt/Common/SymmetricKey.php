<?php

/**
 * Base Class for all \phpseclib\Crypt\* cipher classes
 *
 * PHP version 5
 *
 * Internally for phpseclib developers:
 *  If you plan to add a new cipher class, please note following rules:
 *
 *  - The new \phpseclib\Crypt\* cipher class should extend \phpseclib\Crypt\Common\SymmetricKey
 *
 *  - Following methods are then required to be overridden/overloaded:
 *
 *    - encryptBlock()
 *
 *    - decryptBlock()
 *
 *    - setupKey()
 *
 *  - All other methods are optional to be overridden/overloaded
 *
 *  - Look at the source code of the current ciphers how they extend \phpseclib\Crypt\Common\SymmetricKey
 *    and take one of them as a start up for the new cipher class.
 *
 *  - Please read all the other comments/notes/hints here also for each class var/method
 *
 * @category  Crypt
 * @package   Base
 * @author    Jim Wigginton <terrafrost@php.net>
 * @author    Hans-Juergen Petrich <petrich@tronic-media.com>
 * @copyright 2007 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace phpseclib\Crypt\Common;

use phpseclib\Crypt\Hash;
use phpseclib\Common\Functions\Strings;
use phpseclib\Math\BigInteger;
use phpseclib\Math\BinaryField;
use phpseclib\Math\PrimeField;
use phpseclib\Exception\BadDecryptionException;
use phpseclib\Exception\BadModeException;
use phpseclib\Exception\InconsistentSetupException;
use phpseclib\Exception\InsufficientSetupException;
use phpseclib\Exception\UnsupportedAlgorithmException;

/**
 * Base Class for all \phpseclib\Crypt\* cipher classes
 *
 * @package Base
 * @author  Jim Wigginton <terrafrost@php.net>
 * @author  Hans-Juergen Petrich <petrich@tronic-media.com>
 */
abstract class SymmetricKey
{
    /**#@+
     * @access public
     * @see \phpseclib\Crypt\Common\SymmetricKey::encrypt()
     * @see \phpseclib\Crypt\Common\SymmetricKey::decrypt()
     */
    /**
     * Encrypt / decrypt using the Counter mode.
     *
     * Set to -1 since that's what Crypt/Random.php uses to index the CTR mode.
     *
     * @link http://en.wikipedia.org/wiki/Block_cipher_modes_of_operation#Counter_.28CTR.29
     */
    const MODE_CTR = -1;
    /**
     * Encrypt / decrypt using the Electronic Code Book mode.
     *
     * @link http://en.wikipedia.org/wiki/Block_cipher_modes_of_operation#Electronic_codebook_.28ECB.29
     */
    const MODE_ECB = 1;
    /**
     * Encrypt / decrypt using the Code Book Chaining mode.
     *
     * @link http://en.wikipedia.org/wiki/Block_cipher_modes_of_operation#Cipher-block_chaining_.28CBC.29
     */
    const MODE_CBC = 2;
    /**
     * Encrypt / decrypt using the Cipher Feedback mode.
     *
     * @link http://en.wikipedia.org/wiki/Block_cipher_modes_of_operation#Cipher_feedback_.28CFB.29
     */
    const MODE_CFB = 3;
    /**
     * Encrypt / decrypt using the Cipher Feedback mode (8bit)
     */
    const MODE_CFB8 = 38;
    /**
     * Encrypt / decrypt using the Output Feedback mode.
     *
     * @link http://en.wikipedia.org/wiki/Block_cipher_modes_of_operation#Output_feedback_.28OFB.29
     */
    const MODE_OFB = 4;
    /**
     * Encrypt / decrypt using Galois/Counter mode.
     *
     * @link https://en.wikipedia.org/wiki/Galois/Counter_Mode
     */
    const MODE_GCM = 5;
    /**
     * Encrypt / decrypt using streaming mode.
     */
    const MODE_STREAM = 6;
    /**#@-*/

    /**
     * Mode Map
     *
     * @access private
     * @see \phpseclib\Crypt\Common\SymmetricKey::__construct()
     */
    const MODE_MAP = [
        'ctr'    => self::MODE_CTR,
        'ecb'    => self::MODE_ECB,
        'cbc'    => self::MODE_CBC,
        'cfb'    => self::MODE_CFB,
        'cfb8'   => self::MODE_CFB8,
        'ofb'    => self::MODE_OFB,
        'gcm'    => self::MODE_GCM,
        'stream' => self::MODE_STREAM
    ];

    /**#@+
     * @access private
     * @see \phpseclib\Crypt\Common\SymmetricKey::__construct()
     */
    /**
     * Base value for the internal implementation $engine switch
     */
    const ENGINE_INTERNAL = 1;
    /**
     * Base value for the eval() implementation $engine switch
     */
    const ENGINE_EVAL = 2;
    /**
     * Base value for the mcrypt implementation $engine switch
     */
    const ENGINE_MCRYPT = 3;
    /**
     * Base value for the openssl implementation $engine switch
     */
    const ENGINE_OPENSSL = 4;
    /**
     * Base value for the libsodium implementation $engine switch
     */
    const ENGINE_LIBSODIUM = 5;
    /**
     * Base value for the openssl / gcm implementation $engine switch
     */
    const ENGINE_OPENSSL_GCM = 6;
    /**#@-*/

    /**
     * Engine Reverse Map
     *
     * @access private
     * @see \phpseclib\Crypt\Common\SymmetricKey::getEngine()
     */
    const ENGINE_MAP = [
        self::ENGINE_INTERNAL    => 'PHP',
        self::ENGINE_EVAL        => 'Eval',
        self::ENGINE_MCRYPT      => 'mcrypt',
        self::ENGINE_OPENSSL     => 'OpenSSL',
        self::ENGINE_LIBSODIUM   => 'libsodium',
        self::ENGINE_OPENSSL_GCM => 'OpenSSL (GCM)'
    ];

    /**
     * The Encryption Mode
     *
     * @see self::__construct()
     * @var int
     * @access private
     */
    protected $mode;

    /**
     * The Block Length of the block cipher
     *
     * @var int
     * @access private
     */
    protected $block_size = 16;

    /**
     * The Key
     *
     * @see self::setKey()
     * @var string
     * @access private
     */
    protected $key = false;

    /**
     * The Initialization Vector
     *
     * @see self::setIV()
     * @var string
     * @access private
     */
    private $iv = false;

    /**
     * A "sliding" Initialization Vector
     *
     * @see self::enableContinuousBuffer()
     * @see self::clearBuffers()
     * @var string
     * @access private
     */
    protected $encryptIV;

    /**
     * A "sliding" Initialization Vector
     *
     * @see self::enableContinuousBuffer()
     * @see self::clearBuffers()
     * @var string
     * @access private
     */
    protected $decryptIV;

    /**
     * Continuous Buffer status
     *
     * @see self::enableContinuousBuffer()
     * @var bool
     * @access private
     */
    protected $continuousBuffer = false;

    /**
     * Encryption buffer for CTR, OFB and CFB modes
     *
     * @see self::encrypt()
     * @see self::clearBuffers()
     * @var array
     * @access private
     */
    public $enbuffer;

    /**
     * Decryption buffer for CTR, OFB and CFB modes
     *
     * @see self::decrypt()
     * @see self::clearBuffers()
     * @var array
     * @access private
     */
    private $debuffer;

    /**
     * mcrypt resource for encryption
     *
     * The mcrypt resource can be recreated every time something needs to be created or it can be created just once.
     * Since mcrypt operates in continuous mode, by default, it'll need to be recreated when in non-continuous mode.
     *
     * @see self::encrypt()
     * @var resource
     * @access private
     */
    private $enmcrypt;

    /**
     * mcrypt resource for decryption
     *
     * The mcrypt resource can be recreated every time something needs to be created or it can be created just once.
     * Since mcrypt operates in continuous mode, by default, it'll need to be recreated when in non-continuous mode.
     *
     * @see self::decrypt()
     * @var resource
     * @access private
     */
    private $demcrypt;

    /**
     * Does the enmcrypt resource need to be (re)initialized?
     *
     * @see \phpseclib\Crypt\Twofish::setKey()
     * @see \phpseclib\Crypt\Twofish::setIV()
     * @var bool
     * @access private
     */
    private $enchanged = true;

    /**
     * Does the demcrypt resource need to be (re)initialized?
     *
     * @see \phpseclib\Crypt\Twofish::setKey()
     * @see \phpseclib\Crypt\Twofish::setIV()
     * @var bool
     * @access private
     */
    private $dechanged = true;

    /**
     * mcrypt resource for CFB mode
     *
     * mcrypt's CFB mode, in (and only in) buffered context,
     * is broken, so phpseclib implements the CFB mode by it self,
     * even when the mcrypt php extension is available.
     *
     * In order to do the CFB-mode work (fast) phpseclib
     * use a separate ECB-mode mcrypt resource.
     *
     * @link http://phpseclib.sourceforge.net/cfb-demo.phps
     * @see self::encrypt()
     * @see self::decrypt()
     * @see self::setupMcrypt()
     * @var resource
     * @access private
     */
    private $ecb;

    /**
     * Optimizing value while CFB-encrypting
     *
     * Only relevant if $continuousBuffer enabled
     * and $engine == self::ENGINE_MCRYPT
     *
     * It's faster to re-init $enmcrypt if
     * $buffer bytes > $cfb_init_len than
     * using the $ecb resource furthermore.
     *
     * This value depends of the chosen cipher
     * and the time it would be needed for it's
     * initialization [by mcrypt_generic_init()]
     * which, typically, depends on the complexity
     * on its internaly Key-expanding algorithm.
     *
     * @see self::encrypt()
     * @var int
     * @access private
     */
    protected $cfb_init_len = 600;

    /**
     * Does internal cipher state need to be (re)initialized?
     *
     * @see self::setKey()
     * @see self::setIV()
     * @see self::disableContinuousBuffer()
     * @var bool
     * @access private
     */
    protected $changed = true;

    /**
     * Padding status
     *
     * @see self::enablePadding()
     * @var bool
     * @access private
     */
    private $padding = true;

    /**
     * Is the mode one that is paddable?
     *
     * @see self::__construct()
     * @var bool
     * @access private
     */
    private $paddable = false;

    /**
     * Holds which crypt engine internaly should be use,
     * which will be determined automatically on __construct()
     *
     * Currently available $engines are:
     * - self::ENGINE_LIBSODIUM   (very fast, php-extension: libsodium, extension_loaded('libsodium') required)
     * - self::ENGINE_OPENSSL_GCM (very fast, php-extension: openssl, extension_loaded('openssl') required)
     * - self::ENGINE_OPENSSL     (very fast, php-extension: openssl, extension_loaded('openssl') required)
     * - self::ENGINE_MCRYPT      (fast, php-extension: mcrypt, extension_loaded('mcrypt') required)
     * - self::ENGINE_EVAL        (medium, pure php-engine, no php-extension required)
     * - self::ENGINE_INTERNAL    (slower, pure php-engine, no php-extension required)
     *
     * @see self::setEngine()
     * @see self::encrypt()
     * @see self::decrypt()
     * @var int
     * @access private
     */
    protected $engine;

    /**
     * Holds the preferred crypt engine
     *
     * @see self::setEngine()
     * @see self::setPreferredEngine()
     * @var int
     * @access private
     */
    private $preferredEngine;

    /**
     * The mcrypt specific name of the cipher
     *
     * Only used if $engine == self::ENGINE_MCRYPT
     *
     * @link http://www.php.net/mcrypt_module_open
     * @link http://www.php.net/mcrypt_list_algorithms
     * @see self::setupMcrypt()
     * @var string
     * @access private
     */
    protected $cipher_name_mcrypt;

    /**
     * The openssl specific name of the cipher
     *
     * Only used if $engine == self::ENGINE_OPENSSL
     *
     * @link http://www.php.net/openssl-get-cipher-methods
     * @var string
     * @access private
     */
    protected $cipher_name_openssl;

    /**
     * The openssl specific name of the cipher in ECB mode
     *
     * If OpenSSL does not support the mode we're trying to use (CTR)
     * it can still be emulated with ECB mode.
     *
     * @link http://www.php.net/openssl-get-cipher-methods
     * @var string
     * @access private
     */
    protected static $cipher_name_openssl_ecb;

    /**
     * The default salt used by setPassword()
     *
     * @see self::setPassword()
     * @var string
     * @access private
     */
    private $password_default_salt = 'phpseclib/salt';

    /**
     * The name of the performance-optimized callback function
     *
     * Used by encrypt() / decrypt()
     * only if $engine == self::ENGINE_INTERNAL
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @see self::setupInlineCrypt()
     * @var Callback
     * @access private
     */
    protected $inline_crypt;

    /**
     * If OpenSSL can be used in ECB but not in CTR we can emulate CTR
     *
     * @see self::openssl_ctr_process()
     * @var bool
     * @access private
     */
    private $openssl_emulate_ctr = false;

    /**
     * Don't truncate / null pad key
     *
     * @see self::clearBuffers()
     * @var bool
     * @access private
     */
    private $skip_key_adjustment = false;

    /**
     * Has the key length explicitly been set or should it be derived from the key, itself?
     *
     * @see self::setKeyLength()
     * @var bool
     * @access private
     */
    protected $explicit_key_length = false;

    /**
     * Hash subkey for GHASH
     *
     * @see self::setupGCM()
     * @see self::ghash()
     * @var BinaryField\Integer
     * @access private
     */
    private $h;

    /**
     * Additional authenticated data
     *
     * @var string
     * @access private
     */
    protected $aad = '';

    /**
     * Authentication Tag produced after a round of encryption
     *
     * @var string
     * @access private
     */
    protected $newtag = false;

    /**
     * Authentication Tag to be verified during decryption
     *
     * @var string
     * @access private
     */
    protected $oldtag = false;

    /**
     * GCM Binary Field
     *
     * @see self::__construct()
     * @see self::ghash()
     * @var BinaryField
     * @access private
     */
    private static $gcmField;

    /**
     * Poly1305 Prime Field
     *
     * @see self::enablePoly1305()
     * @see self::poly1305()
     * @var PrimeField
     * @access private
     */
    private static $poly1305Field;

    /**
     * Poly1305 Key
     *
     * @see self::setPoly1305Key()
     * @see self::poly1305()
     * @var string
     * @access private
     */
    protected $poly1305Key;

    /**
     * Poly1305 Flag
     *
     * @see self::setPoly1305Key()
     * @see self::enablePoly1305()
     * @var boolean
     * @access private
     */
    protected $usePoly1305 = false;

    /**
     * The Original Initialization Vector
     *
     * GCM uses the nonce to build the IV but we want to be able to distinguish between nonce-derived
     * IV's and user-set IV's
     *
     * @see self::setIV()
     * @var string
     * @access private
     */
    private $origIV = false;

    /**
     * Nonce
     *
     * Only used with GCM. We could re-use setIV() but nonce's can be of a different length and
     * toggling between GCM and other modes could be more complicated if we re-used setIV()
     *
     * @see self::setNonce()
     * @var string
     * @access private
     */
    protected $nonce = false;

    /**
     * Default Constructor.
     *
     * $mode could be:
     *
     * - ecb
     *
     * - cbc
     *
     * - ctr
     *
     * - cfb
     *
     * - cfb8
     *
     * - ofb
     *
     * - gcm
     *
     * @param string $mode
     * @access public
     * @throws BadModeException if an invalid / unsupported mode is provided
     */
    public function __construct($mode)
    {
        $mode = strtolower($mode);
        // necessary because of 5.6 compatibility; we can't do isset(self::MODE_MAP[$mode]) in 5.6
        $map = self::MODE_MAP;
        if (!isset($map[$mode])) {
            throw new BadModeException('No valid mode has been specified');
        }

        $mode = self::MODE_MAP[$mode];

        // $mode dependent settings
        switch ($mode) {
            case self::MODE_ECB:
            case self::MODE_CBC:
                $this->paddable = true;
                break;
            case self::MODE_CTR:
            case self::MODE_CFB:
            case self::MODE_CFB8:
            case self::MODE_OFB:
            case self::MODE_STREAM:
                $this->paddable = false;
                break;
            case self::MODE_GCM:
                if ($this->block_size != 16) {
                    throw new BadModeException('GCM is only valid for block ciphers with a block size of 128 bits');
                }
                if (!isset(self::$gcmField)) {
                    self::$gcmField = new BinaryField(128, 7, 2, 1, 0);
                }
                $this->paddable = false;
                break;
            default:
                throw new BadModeException('No valid mode has been specified');
        }

        $this->mode = $mode;
    }

    /**
     * Sets the initialization vector.
     *
     * setIV() is not required when ecb or gcm modes are being used.
     *
     * @access public
     * @param string $iv
     * @throws \LengthException if the IV length isn't equal to the block size
     * @throws \BadMethodCallException if an IV is provided when one shouldn't be
     * @internal Can be overwritten by a sub class, but does not have to be
     */
    public function setIV($iv)
    {
        if ($this->mode == self::MODE_ECB) {
            throw new \BadMethodCallException('This mode does not require an IV.');
        }

        if ($this->mode == self::MODE_GCM) {
            throw new \BadMethodCallException('Use setNonce instead');
        }

        if (!$this->usesIV()) {
            throw new \BadMethodCallException('This algorithm does not use an IV.');
        }

        if (strlen($iv) != $this->block_size) {
            throw new \LengthException('Received initialization vector of size ' . strlen($iv) . ', but size ' . $this->block_size . ' is required');
        }

        $this->iv = $this->origIV = $iv;
        $this->changed = true;
    }

    /**
     * Enables Poly1305 mode.
     *
     * Once enabled Poly1305 cannot be disabled.
     *
     * @access public
     * @throws \BadMethodCallException if Poly1305 is enabled whilst in GCM mode
     */
    public function enablePoly1305()
    {
        if ($this->mode == self::MODE_GCM) {
            throw new \BadMethodCallException('Poly1305 cannot be used in GCM mode');
        }

        $this->usePoly1305 = true;
    }

    /**
     * Enables Poly1305 mode.
     *
     * Once enabled Poly1305 cannot be disabled. If $key is not passed then an attempt to call createPoly1305Key
     * will be made.
     *
     * @access public
     * @param string $key optional
     * @throws \LengthException if the key isn't long enough
     * @throws \BadMethodCallException if Poly1305 is enabled whilst in GCM mode
     */
    public function setPoly1305Key($key = null)
    {
        if ($this->mode == self::MODE_GCM) {
            throw new \BadMethodCallException('Poly1305 cannot be used in GCM mode');
        }

        if (!is_string($key) || strlen($key) != 32) {
            throw new \LengthException('The Poly1305 key must be 32 bytes long (256 bits)');
        }

        if (!isset(self::$poly1305Field)) {
            // 2^130-5
            self::$poly1305Field = new PrimeField(new BigInteger('3fffffffffffffffffffffffffffffffb', 16));
        }

        $this->poly1305Key = $key;
        $this->usePoly1305 = true;
    }

    /**
     * Sets the nonce.
     *
     * setNonce() is only required when gcm is used
     *
     * @access public
     * @param string $nonce
     * @throws \BadMethodCallException if an nonce is provided when one shouldn't be
     */
    public function setNonce($nonce)
    {
        if ($this->mode != self::MODE_GCM) {
            throw new \BadMethodCallException('Nonces are only used in GCM mode.');
        }

        $this->nonce = $nonce;
        $this->changed = true;
        $this->setEngine();
    }

    /**
     * Sets additional authenticated data
     *
     * setAAD() is only used by gcm or in poly1305 mode
     *
     * @access public
     * @param string $aad
     * @throws \BadMethodCallException if mode isn't GCM or if poly1305 isn't being utilized
     */
    public function setAAD($aad)
    {
        if ($this->mode != self::MODE_GCM && !$this->usePoly1305) {
            throw new \BadMethodCallException('Additional authenticated data is only utilized in GCM mode or with Poly1305');
        }

        $this->aad = $aad;
    }

    /**
     * Returns whether or not the algorithm uses an IV
     *
     * @access public
     * @return bool
     */
    public function usesIV()
    {
        return $this->mode != self::MODE_GCM;
    }

    /**
     * Returns whether or not the algorithm uses a nonce
     *
     * @access public
     * @return bool
     */
    public function usesNonce()
    {
        return $this->mode == self::MODE_GCM;
    }

    /**
     * Returns the current key length in bits
     *
     * @access public
     * @return int
     */
    public function getKeyLength()
    {
        return $this->key_length << 3;
    }

    /**
     * Returns the current block length in bits
     *
     * @access public
     * @return int
     */
    public function getBlockLength()
    {
        return $this->block_size << 3;
    }

    /**
     * Returns the current block length in bytes
     *
     * @access public
     * @return int
     */
    public function getBlockLengthInBytes()
    {
        return $this->block_size;
    }

    /**
     * Sets the key length.
     *
     * Keys with explicitly set lengths need to be treated accordingly
     *
     * @access public
     * @param int $length
     */
    public function setKeyLength($length)
    {
        $this->explicit_key_length = $length >> 3;

        if (is_string($this->key) && strlen($this->key) != $this->explicit_key_length) {
            $this->key = false;
            throw new InconsistentSetupException('Key has already been set and is not ' .$this->explicit_key_length . ' bytes long');
        }
    }

    /**
     * Sets the key.
     *
     * The min/max length(s) of the key depends on the cipher which is used.
     * If the key not fits the length(s) of the cipher it will paded with null bytes
     * up to the closest valid key length.  If the key is more than max length,
     * we trim the excess bits.
     *
     * If the key is not explicitly set, it'll be assumed to be all null bytes.
     *
     * @access public
     * @param string $key
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function setKey($key)
    {
        if ($this->explicit_key_length !== false && strlen($key) != $this->explicit_key_length) {
            throw new InconsistentSetupException('Key length has already been set to ' . $this->explicit_key_length . ' bytes and this key is ' . strlen($key) . ' bytes');
        }

        $this->key = $key;
        $this->key_length = strlen($key);
        $this->changed = true;
        $this->setEngine();
    }

    /**
     * Sets the password.
     *
     * Depending on what $method is set to, setPassword()'s (optional) parameters are as follows:
     *     {@link http://en.wikipedia.org/wiki/PBKDF2 pbkdf2} or pbkdf1:
     *         $hash, $salt, $count, $dkLen
     *
     *         Where $hash (default = sha1) currently supports the following hashes: see: Crypt/Hash.php
     *
     * @see Crypt/Hash.php
     * @param string $password
     * @param string $method
     * @param $func_args[]
     * @throws \LengthException if pbkdf1 is being used and the derived key length exceeds the hash length
     * @return bool
     * @access public
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function setPassword($password, $method = 'pbkdf2', ...$func_args)
    {
        $key = '';

        $method = strtolower($method);
        switch ($method) {
            case 'pkcs12': // from https://tools.ietf.org/html/rfc7292#appendix-B.2
            case 'pbkdf1':
            case 'pbkdf2':
                // Hash function
                $hash = isset($func_args[0]) ? strtolower($func_args[0]) : 'sha1';
                $hashObj = new Hash();
                $hashObj->setHash($hash);

                // WPA and WPA2 use the SSID as the salt
                $salt = isset($func_args[1]) ? $func_args[1] : $this->password_default_salt;

                // RFC2898#section-4.2 uses 1,000 iterations by default
                // WPA and WPA2 use 4,096.
                $count = isset($func_args[2]) ? $func_args[2] : 1000;

                // Keylength
                if (isset($func_args[3])) {
                    if ($func_args[3] <= 0) {
                        throw new \LengthException('Derived key length cannot be longer 0 or less');
                    }
                    $dkLen = $func_args[3];
                } else {
                    $key_length = $this->explicit_key_length !== false ? $this->explicit_key_length : $this->key_length;
                    $dkLen = $method == 'pbkdf1' ? 2 * $key_length : $key_length;
                }

                switch (true) {
                    case $method == 'pkcs12':
                        /*
                         In this specification, however, all passwords are created from
                         BMPStrings with a NULL terminator.  This means that each character in
                         the original BMPString is encoded in 2 bytes in big-endian format
                         (most-significant byte first).  There are no Unicode byte order
                         marks.  The 2 bytes produced from the last character in the BMPString
                         are followed by 2 additional bytes with the value 0x00.

                         -- https://tools.ietf.org/html/rfc7292#appendix-B.1
                         */
                        $password = "\0". chunk_split($password, 1, "\0") . "\0";

                        /*
                         This standard specifies 3 different values for the ID byte mentioned
                         above:

                         1.  If ID=1, then the pseudorandom bits being produced are to be used
                             as key material for performing encryption or decryption.

                         2.  If ID=2, then the pseudorandom bits being produced are to be used
                             as an IV (Initial Value) for encryption or decryption.

                         3.  If ID=3, then the pseudorandom bits being produced are to be used
                             as an integrity key for MACing.
                         */
                        // Construct a string, D (the "diversifier"), by concatenating v/8
                        // copies of ID.
                        $blockLength = $hashObj->getBlockLengthInBytes();
                        $d1 = str_repeat(chr(1), $blockLength);
                        $d2 = str_repeat(chr(2), $blockLength);
                        $s = '';
                        if (strlen($salt)) {
                            while (strlen($s) < $blockLength) {
                                $s.= $salt;
                            }
                        }
                        $s = substr($s, 0, $blockLength);

                        $p = '';
                        if (strlen($password)) {
                            while (strlen($p) < $blockLength) {
                                $p.= $password;
                            }
                        }
                        $p = substr($p, 0, $blockLength);

                        $i = $s . $p;

                        $this->setKey(self::pkcs12helper($dkLen, $hashObj, $i, $d1, $count));
                        if ($this->usesIV()) {
                            $this->setIV(self::pkcs12helper($this->block_size, $hashObj, $i, $d2, $count));
                        }

                        return true;
                    case $method == 'pbkdf1':
                        if ($dkLen > $hashObj->getLengthInBytes()) {
                            throw new \LengthException('Derived key length cannot be longer than the hash length');
                        }
                        $t = $password . $salt;
                        for ($i = 0; $i < $count; ++$i) {
                            $t = $hashObj->hash($t);
                        }
                        $key = substr($t, 0, $dkLen);

                        $this->setKey(substr($key, 0, $dkLen >> 1));
                        if ($this->usesIV()) {
                            $this->setIV(substr($key, $dkLen >> 1));
                        }

                        return true;
                    case !in_array($hash, hash_algos()):
                        $i = 1;
                        $hashObj->setKey($password);
                        while (strlen($key) < $dkLen) {
                            $f = $u = $hashObj->hash($salt . pack('N', $i++));
                            for ($j = 2; $j <= $count; ++$j) {
                                $u = $hashObj->hash($u);
                                $f^= $u;
                            }
                            $key.= $f;
                        }
                        $key = substr($key, 0, $dkLen);
                        break;
                    default:
                        $key = hash_pbkdf2($hash, $password, $salt, $count, $dkLen, true);
                }
                break;
            default:
                throw new UnsupportedAlgorithmException($method . ' is not a supported password hashing method');
        }

        $this->setKey($key);

        return true;
    }

    /**
     * PKCS#12 KDF Helper Function
     *
     * As discussed here:
     *
     * {@link https://tools.ietf.org/html/rfc7292#appendix-B}
     *
     * @see self::setPassword()
     * @access private
     * @param int $n
     * @param \phpseclib\Crypt\Hash $hashObj
     * @param string $i
     * @param string $d
     * @param int $count
     * @return string $a
     */
    private static function pkcs12helper($n, $hashObj, $i, $d, $count)
    {
        static $one;
        if (!isset($one)) {
            $one = new BigInteger(1);
        }

        $blockLength = $hashObj->getBlockLength() >> 3;

        $c = ceil($n / $hashObj->getLengthInBytes());
        $a = '';
        for ($j = 1; $j <= $c; $j++) {
            $ai = $d . $i;
            for ($k = 0; $k < $count; $k++) {
                $ai = $hashObj->hash($ai);
            }
            $b = '';
            while (strlen($b) < $blockLength) {
                $b.= $ai;
            }
            $b = substr($b, 0, $blockLength);
            $b = new BigInteger($b, 256);
            $newi = '';
            for ($k = 0; $k < strlen($i); $k+= $blockLength) {
                $temp = substr($i, $k, $blockLength);
                $temp = new BigInteger($temp, 256);
                $temp->setPrecision($blockLength << 3);
                $temp = $temp->add($b);
                $temp = $temp->add($one);
                $newi.= $temp->toBytes(false);
            }
            $i = $newi;
            $a.= $ai;
        }

        return substr($a, 0, $n);
    }

    /**
     * Encrypts a message.
     *
     * $plaintext will be padded with additional bytes such that it's length is a multiple of the block size. Other cipher
     * implementations may or may not pad in the same manner.  Other common approaches to padding and the reasons why it's
     * necessary are discussed in the following
     * URL:
     *
     * {@link http://www.di-mgt.com.au/cryptopad.html http://www.di-mgt.com.au/cryptopad.html}
     *
     * An alternative to padding is to, separately, send the length of the file.  This is what SSH, in fact, does.
     * strlen($plaintext) will still need to be a multiple of the block size, however, arbitrary values can be added to make it that
     * length.
     *
     * @see self::decrypt()
     * @access public
     * @param string $plaintext
     * @return string $ciphertext
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function encrypt($plaintext)
    {
        if ($this->paddable) {
            $plaintext = $this->pad($plaintext);
        }

        $this->setup();









/*
            static $sb_0, $sb_1, $sb_2, $sb_3;
            if (!$sb_0) {
                $sb_0 = $this->bctx["sb"][0];
                $sb_1 = $this->bctx["sb"][1];
                $sb_2 = $this->bctx["sb"][2];
                $sb_3 = $this->bctx["sb"][3];
            }





$_text = $plaintext;

                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);
                    $_xor = $this->encryptIV;
                    $_buffer = &$this->enbuffer;
                    if (strlen($_buffer["ciphertext"])) {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= 8) {
                            $_block = substr($_text, $_i, 8);
                            if (strlen($_block) > strlen($_buffer["ciphertext"])) {
                                $in = $_xor;
                                
            $in = unpack("N*", $in);
            $l = $in[1];
            $r = $in[2];
        
                $l^= 549779041;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 1343762651;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 3920224580;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 2647197573;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 762926544;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 1139411722;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 1885981652;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 1235852190;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 908823431;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 3377844777;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 3493379003;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 2034177831;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 154927566;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 3674330545;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 835161592;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 4139394170;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
            $in = pack("N*",
                $r ^ 733410383,
                $l ^ 818193079
            );
        
                                \phpseclib\Common\Functions\Strings::increment_str($_xor);
                                $_buffer["ciphertext"].= $in;
                            }
                            $_key = \phpseclib\Common\Functions\Strings::shift($_buffer["ciphertext"], 8);
                            $_ciphertext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= 8) {
                            $_block = substr($_text, $_i, 8);
                            $in = $_xor;
                            
            $in = unpack("N*", $in);
            $l = $in[1];
            $r = $in[2];
        
                $l^= 549779041;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 1343762651;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 3920224580;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 2647197573;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 762926544;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 1139411722;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 1885981652;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 1235852190;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 908823431;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 3377844777;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 3493379003;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 2034177831;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 154927566;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 3674330545;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
                $l^= 835161592;
                $r^= ($sb_0[$l >> 24 & 0xff] + $sb_1[$l >> 16 & 0xff] ^
                      $sb_2[$l >>  8 & 0xff]) +
                      $sb_3[$l       & 0xff];

                $r^= 4139394170;
                $l^= ($sb_0[$r >> 24 & 0xff] + $sb_1[$r >> 16 & 0xff]  ^
                      $sb_2[$r >>  8 & 0xff]) +
                      $sb_3[$r       & 0xff];
            
            $in = pack("N*",
                $r ^ 733410383,
                $l ^ 818193079
            );
        
                            \phpseclib\Common\Functions\Strings::increment_str($_xor);
                            $_key = $in;
                            $_ciphertext.= $_block ^ $_key;
                        }
                    }
                    if ($this->continuousBuffer) {
                        $this->encryptIV = $_xor;
                        if ($_start = $_plaintext_len % 8) {
                            $_buffer["ciphertext"] = substr($_key, $_start) . $_buffer["ciphertext"];
                        }
                    }

                    return $_ciphertext;
*/







        //if ($this->engine === self::ENGINE_EVAL) {
            $inline = $this->inline_crypt;
//var_dump($inline); exit;
            return $inline('encrypt', $plaintext);
        //}
    }

    /**
     * Decrypts a message.
     *
     * If strlen($ciphertext) is not a multiple of the block size, null bytes will be added to the end of the string until
     * it is.
     *
     * @see self::encrypt()
     * @access public
     * @param string $ciphertext
     * @return string $plaintext
     * @throws \LengthException if we're inside a block cipher and the ciphertext length is not a multiple of the block size
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function decrypt($ciphertext)
    {
        if ($this->paddable && strlen($ciphertext) % $this->block_size) {
            throw new \LengthException('The ciphertext length (' . strlen($ciphertext) . ') needs to be a multiple of the block size (' . $this->block_size . ')');
        }

        $this->setup();

        if ($this->mode == self::MODE_GCM || isset($this->poly1305Key)) {
            if ($this->oldtag === false) {
                throw new InsufficientSetupException('Authentication Tag has not been set');
            }

            if (isset($this->poly1305Key)) {
                $newtag = $this->poly1305($ciphertext);
            } else {
                $oldIV = $this->iv;
                Strings::increment_str($this->iv);
                $cipher = new static('ctr');
                $cipher->setKey($this->key);
                $cipher->setIV($this->iv);
                $plaintext = $cipher->decrypt($ciphertext);

                $s = $this->ghash(
                    self::nullPad128($this->aad) .
                    self::nullPad128($ciphertext) .
                    self::len64($this->aad) .
                    self::len64($ciphertext)
                );
                $cipher->encryptIV = $this->iv = $this->encryptIV = $this->decryptIV = $oldIV;
                $newtag = $cipher->encrypt($s);
            }
            if ($this->oldtag != substr($newtag, 0, strlen($newtag))) {
                $cipher = clone $this;
                unset($cipher->poly1305Key);
                $this->usePoly1305 = false;
                $plaintext = $cipher->decrypt($ciphertext);
                $this->oldtag = false;
                throw new BadDecryptionException('Derived authentication tag and supplied authentication tag do not match');
            }
            $this->oldtag = false;
            return $plaintext;
        }

        if ($this->engine === self::ENGINE_OPENSSL) {
            switch ($this->mode) {
                case self::MODE_STREAM:
                    $plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
                    break;
                case self::MODE_ECB:
                    $plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
                    break;
                case self::MODE_CBC:
                    $offset = $this->block_size;
                    $plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->decryptIV);
                    if ($this->continuousBuffer) {
                        $this->decryptIV = substr($ciphertext, -$offset, $this->block_size);
                    }
                    break;
                case self::MODE_CTR:
                    $plaintext = $this->openssl_ctr_process($ciphertext, $this->decryptIV, $this->debuffer);
                    break;
                case self::MODE_CFB:
                    // cfb loosely routines inspired by openssl's:
                    // {@link http://cvs.openssl.org/fileview?f=openssl/crypto/modes/cfb128.c&v=1.3.2.2.2.1}
                    $plaintext = '';
                    if ($this->continuousBuffer) {
                        $iv = &$this->decryptIV;
                        $pos = &$this->buffer['pos'];
                    } else {
                        $iv = $this->decryptIV;
                        $pos = 0;
                    }
                    $len = strlen($ciphertext);
                    $i = 0;
                    if ($pos) {
                        $orig_pos = $pos;
                        $max = $this->block_size - $pos;
                        if ($len >= $max) {
                            $i = $max;
                            $len-= $max;
                            $pos = 0;
                        } else {
                            $i = $len;
                            $pos+= $len;
                            $len = 0;
                        }
                        // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $this->blocksize
                        $plaintext = substr($iv, $orig_pos) ^ $ciphertext;
                        $iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
                        $ciphertext = substr($ciphertext, $i);
                    }
                    $overflow = $len % $this->block_size;
                    if ($overflow) {
                        $plaintext.= openssl_decrypt(substr($ciphertext, 0, -$overflow), $this->cipher_name_openssl, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
                        if ($len - $overflow) {
                            $iv = substr($ciphertext, -$overflow - $this->block_size, -$overflow);
                        }
                        $iv = openssl_encrypt(str_repeat("\0", $this->block_size), $this->cipher_name_openssl, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
                        $plaintext.= $iv ^ substr($ciphertext, -$overflow);
                        $iv = substr_replace($iv, substr($ciphertext, -$overflow), 0, $overflow);
                        $pos = $overflow;
                    } elseif ($len) {
                        $plaintext.= openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
                        $iv = substr($ciphertext, -$this->block_size);
                    }
                    break;
                case self::MODE_CFB8:
                    $plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->decryptIV);
                    if ($this->continuousBuffer) {
                        if (($len = strlen($ciphertext)) >= $this->block_size) {
                            $this->decryptIV = substr($ciphertext, -$this->block_size);
                        } else {
                            $this->decryptIV = substr($this->decryptIV, $len - $this->block_size) . substr($ciphertext, -$len);
                        }
                    }
                    break;
                case self::MODE_OFB:
                    $plaintext = $this->openssl_ofb_process($ciphertext, $this->decryptIV, $this->debuffer);
            }

            return $this->paddable ? $this->unpad($plaintext) : $plaintext;
        }

        if ($this->engine === self::ENGINE_MCRYPT) {
            $block_size = $this->block_size;
            if ($this->dechanged) {
                @mcrypt_generic_init($this->demcrypt, $this->key, $this->getIV($this->decryptIV));
                $this->dechanged = false;
            }

            if ($this->mode == self::MODE_CFB && $this->continuousBuffer) {
                $iv = &$this->decryptIV;
                $pos = &$this->debuffer['pos'];
                $len = strlen($ciphertext);
                $plaintext = '';
                $i = 0;
                if ($pos) {
                    $orig_pos = $pos;
                    $max = $block_size - $pos;
                    if ($len >= $max) {
                        $i = $max;
                        $len-= $max;
                        $pos = 0;
                    } else {
                        $i = $len;
                        $pos+= $len;
                        $len = 0;
                    }
                    // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $blocksize
                    $plaintext = substr($iv, $orig_pos) ^ $ciphertext;
                    $iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
                }
                if ($len >= $block_size) {
                    $cb = substr($ciphertext, $i, $len - $len % $block_size);
                    $plaintext.= @mcrypt_generic($this->ecb, $iv . $cb) ^ $cb;
                    $iv = substr($cb, -$block_size);
                    $len%= $block_size;
                }
                if ($len) {
                    $iv = @mcrypt_generic($this->ecb, $iv);
                    $plaintext.= $iv ^ substr($ciphertext, -$len);
                    $iv = substr_replace($iv, substr($ciphertext, -$len), 0, $len);
                    $pos = $len;
                }

                return $plaintext;
            }

            $plaintext = @mdecrypt_generic($this->demcrypt, $ciphertext);

            if (!$this->continuousBuffer) {
                @mcrypt_generic_init($this->demcrypt, $this->key, $this->getIV($this->decryptIV));
            }

            return $this->paddable ? $this->unpad($plaintext) : $plaintext;
        }

        if ($this->engine === self::ENGINE_EVAL) {
            $inline = $this->inline_crypt;
            return $inline('decrypt', $ciphertext);
        }

        $block_size = $this->block_size;

        $buffer = &$this->debuffer;
        $plaintext = '';
        switch ($this->mode) {
            case self::MODE_ECB:
                for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                    $plaintext.= $this->decryptBlock(substr($ciphertext, $i, $block_size));
                }
                break;
            case self::MODE_CBC:
                $xor = $this->decryptIV;
                for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                    $block = substr($ciphertext, $i, $block_size);
                    $plaintext.= $this->decryptBlock($block) ^ $xor;
                    $xor = $block;
                }
                if ($this->continuousBuffer) {
                    $this->decryptIV = $xor;
                }
                break;
            case self::MODE_CTR:
                $xor = $this->decryptIV;
                if (strlen($buffer['ciphertext'])) {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $block = substr($ciphertext, $i, $block_size);
                        if (strlen($block) > strlen($buffer['ciphertext'])) {
                            $buffer['ciphertext'].= $this->encryptBlock($xor);
                        }
                        Strings::increment_str($xor);
                        $key = Strings::shift($buffer['ciphertext'], $block_size);
                        $plaintext.= $block ^ $key;
                    }
                } else {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $block = substr($ciphertext, $i, $block_size);
                        $key = $this->encryptBlock($xor);
                        Strings::increment_str($xor);
                        $plaintext.= $block ^ $key;
                    }
                }
                if ($this->continuousBuffer) {
                    $this->decryptIV = $xor;
                    if ($start = strlen($ciphertext) % $block_size) {
                        $buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
                    }
                }
                break;
            case self::MODE_CFB:
                if ($this->continuousBuffer) {
                    $iv = &$this->decryptIV;
                    $pos = &$buffer['pos'];
                } else {
                    $iv = $this->decryptIV;
                    $pos = 0;
                }
                $len = strlen($ciphertext);
                $i = 0;
                if ($pos) {
                    $orig_pos = $pos;
                    $max = $block_size - $pos;
                    if ($len >= $max) {
                        $i = $max;
                        $len-= $max;
                        $pos = 0;
                    } else {
                        $i = $len;
                        $pos+= $len;
                        $len = 0;
                    }
                    // ie. $i = min($max, $len), $len-= $i, $pos+= $i, $pos%= $blocksize
                    $plaintext = substr($iv, $orig_pos) ^ $ciphertext;
                    $iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
                }
                while ($len >= $block_size) {
                    $iv = $this->encryptBlock($iv);
                    $cb = substr($ciphertext, $i, $block_size);
                    $plaintext.= $iv ^ $cb;
                    $iv = $cb;
                    $len-= $block_size;
                    $i+= $block_size;
                }
                if ($len) {
                    $iv = $this->encryptBlock($iv);
                    $plaintext.= $iv ^ substr($ciphertext, $i);
                    $iv = substr_replace($iv, substr($ciphertext, $i), 0, $len);
                    $pos = $len;
                }
                break;
            case self::MODE_CFB8:
                $plaintext = '';
                $len = strlen($ciphertext);
                $iv = $this->decryptIV;

                for ($i = 0; $i < $len; ++$i) {
                    $plaintext .= $ciphertext[$i] ^ $this->encryptBlock($iv);
                    $iv = substr($iv, 1) . $ciphertext[$i];
                }

                if ($this->continuousBuffer) {
                    if ($len >= $block_size) {
                        $this->decryptIV = substr($ciphertext, -$block_size);
                    } else {
                        $this->decryptIV = substr($this->decryptIV, $len - $block_size) . substr($ciphertext, -$len);
                    }
                }
                break;
            case self::MODE_OFB:
                $xor = $this->decryptIV;
                if (strlen($buffer['xor'])) {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $block = substr($ciphertext, $i, $block_size);
                        if (strlen($block) > strlen($buffer['xor'])) {
                            $xor = $this->encryptBlock($xor);
                            $buffer['xor'].= $xor;
                        }
                        $key = Strings::shift($buffer['xor'], $block_size);
                        $plaintext.= $block ^ $key;
                    }
                } else {
                    for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
                        $xor = $this->encryptBlock($xor);
                        $plaintext.= substr($ciphertext, $i, $block_size) ^ $xor;
                    }
                    $key = $xor;
                }
                if ($this->continuousBuffer) {
                    $this->decryptIV = $xor;
                    if ($start = strlen($ciphertext) % $block_size) {
                        $buffer['xor'] = substr($key, $start) . $buffer['xor'];
                    }
                }
                break;
            case self::MODE_STREAM:
                $plaintext = $this->decryptBlock($ciphertext);
                break;
        }
        return $this->paddable ? $this->unpad($plaintext) : $plaintext;
    }

    /**
     * Get the authentication tag
     *
     * Only used in GCM or Poly1305 mode
     *
     * @see self::encrypt()
     * @param int $length optional
     * @return string
     * @access public
     * @throws \LengthException if $length isn't of a sufficient length
     * @throws \RuntimeException if GCM mode isn't being used
     */
    public function getTag($length = 16)
    {
        if ($this->mode != self::MODE_GCM && !$this->usePoly1305) {
            throw new \BadMethodCallException('Authentication tags are only utilized in GCM mode or with Poly1305');
        }

        if ($this->newtag === false) {
            throw new \BadMethodCallException('A tag can only be returned after a round of encryption has been performed');
        }

        // the tag is 128-bits. it can't be greater than 16 bytes because that's bigger than the tag is. if it
        // were 0 you might as well be doing CTR and less than 4 provides minimal security that could be trivially
        // easily brute forced.
        // see https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-38d.pdf#page=36
        // for more info
        if ($length < 4 || $length > 16) {
            throw new \LengthException('The authentication tag must be between 4 and 16 bytes long');
        }

        return $length == 16 ?
            $this->newtag :
            substr($this->newtag, 0, $length);
    }

    /**
     * Sets the authentication tag
     *
     * Only used in GCM mode
     *
     * @see self::decrypt()
     * @param string $tag
     * @access public
     * @throws \LengthException if $length isn't of a sufficient length
     * @throws \RuntimeException if GCM mode isn't being used
     */
    public function setTag($tag)
    {
        if ($this->usePoly1305 && !isset($this->poly1305Key) && method_exists($this, 'createPoly1305Key')) {
            $this->createPoly1305Key();
        }

        if ($this->mode != self::MODE_GCM && !$this->usePoly1305) {
            throw new \BadMethodCallException('Authentication tags are only utilized in GCM mode or with Poly1305');
        }

        $length = strlen($tag);
        if ($length < 4 || $length > 16) {
            throw new \LengthException('The authentication tag must be between 4 and 16 bytes long');
        }
        $this->oldtag = $tag;
    }

    /**
     * Get the IV
     *
     * mcrypt requires an IV even if ECB is used
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @param string $iv
     * @return string
     * @access private
     */
    protected function getIV($iv)
    {
        return $this->mode == self::MODE_ECB ? str_repeat("\0", $this->block_size) : $iv;
    }

    /**
     * OpenSSL CTR Processor
     *
     * PHP's OpenSSL bindings do not operate in continuous mode so we'll wrap around it. Since the keystream
     * for CTR is the same for both encrypting and decrypting this function is re-used by both SymmetricKey::encrypt()
     * and SymmetricKey::decrypt(). Also, OpenSSL doesn't implement CTR for all of it's symmetric ciphers so this
     * function will emulate CTR with ECB when necessary.
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @param string $plaintext
     * @param string $encryptIV
     * @param array $buffer
     * @return string
     * @access private
     */
    private function openssl_ctr_process($plaintext, &$encryptIV, &$buffer)
    {
        $ciphertext = '';

        $block_size = $this->block_size;
        $key = $this->key;

        if ($this->openssl_emulate_ctr) {
            $xor = $encryptIV;
            if (strlen($buffer['ciphertext'])) {
                for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                    $block = substr($plaintext, $i, $block_size);
                    if (strlen($block) > strlen($buffer['ciphertext'])) {
                        $buffer['ciphertext'].= openssl_encrypt($xor, static::$cipher_name_openssl_ecb, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
                    }
                    Strings::increment_str($xor);
                    $otp = Strings::shift($buffer['ciphertext'], $block_size);
                    $ciphertext.= $block ^ $otp;
                }
            } else {
                for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
                    $block = substr($plaintext, $i, $block_size);
                    $otp = openssl_encrypt($xor, static::$cipher_name_openssl_ecb, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
                    Strings::increment_str($xor);
                    $ciphertext.= $block ^ $otp;
                }
            }
            if ($this->continuousBuffer) {
                $encryptIV = $xor;
                if ($start = strlen($plaintext) % $block_size) {
                    $buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
                }
            }

            return $ciphertext;
        }

        if (strlen($buffer['ciphertext'])) {
            $ciphertext = $plaintext ^ Strings::shift($buffer['ciphertext'], strlen($plaintext));
            $plaintext = substr($plaintext, strlen($ciphertext));

            if (!strlen($plaintext)) {
                return $ciphertext;
            }
        }

        $overflow = strlen($plaintext) % $block_size;
        if ($overflow) {
            $plaintext2 = Strings::pop($plaintext, $overflow); // ie. trim $plaintext to a multiple of $block_size and put rest of $plaintext in $plaintext2
            $encrypted = openssl_encrypt($plaintext . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $encryptIV);
            $temp = Strings::pop($encrypted, $block_size);
            $ciphertext.= $encrypted . ($plaintext2 ^ $temp);
            if ($this->continuousBuffer) {
                $buffer['ciphertext'] = substr($temp, $overflow);
                $encryptIV = $temp;
            }
        } elseif (!strlen($buffer['ciphertext'])) {
            $ciphertext.= openssl_encrypt($plaintext . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $encryptIV);
            $temp = Strings::pop($ciphertext, $block_size);
            if ($this->continuousBuffer) {
                $encryptIV = $temp;
            }
        }
        if ($this->continuousBuffer) {
            $encryptIV = openssl_decrypt($encryptIV, static::$cipher_name_openssl_ecb, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
            if ($overflow) {
                Strings::increment_str($encryptIV);
            }
        }

        return $ciphertext;
    }

    /**
     * OpenSSL OFB Processor
     *
     * PHP's OpenSSL bindings do not operate in continuous mode so we'll wrap around it. Since the keystream
     * for OFB is the same for both encrypting and decrypting this function is re-used by both SymmetricKey::encrypt()
     * and SymmetricKey::decrypt().
     *
     * @see self::encrypt()
     * @see self::decrypt()
     * @param string $plaintext
     * @param string $encryptIV
     * @param array $buffer
     * @return string
     * @access private
     */
    private function openssl_ofb_process($plaintext, &$encryptIV, &$buffer)
    {
        if (strlen($buffer['xor'])) {
            $ciphertext = $plaintext ^ $buffer['xor'];
            $buffer['xor'] = substr($buffer['xor'], strlen($ciphertext));
            $plaintext = substr($plaintext, strlen($ciphertext));
        } else {
            $ciphertext = '';
        }

        $block_size = $this->block_size;

        $len = strlen($plaintext);
        $key = $this->key;
        $overflow = $len % $block_size;

        if (strlen($plaintext)) {
            if ($overflow) {
                $ciphertext.= openssl_encrypt(substr($plaintext, 0, -$overflow) . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $encryptIV);
                $xor = Strings::pop($ciphertext, $block_size);
                if ($this->continuousBuffer) {
                    $encryptIV = $xor;
                }
                $ciphertext.= Strings::shift($xor, $overflow) ^ substr($plaintext, -$overflow);
                if ($this->continuousBuffer) {
                    $buffer['xor'] = $xor;
                }
            } else {
                $ciphertext = openssl_encrypt($plaintext, $this->cipher_name_openssl, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $encryptIV);
                if ($this->continuousBuffer) {
                    $encryptIV = substr($ciphertext, -$block_size) ^ substr($plaintext, -$block_size);
                }
            }
        }

        return $ciphertext;
    }

    /**
     * phpseclib <-> OpenSSL Mode Mapper
     *
     * May need to be overwritten by classes extending this one in some cases
     *
     * @return string
     * @access private
     */
    protected function openssl_translate_mode()
    {
        switch ($this->mode) {
            case self::MODE_ECB:
                return 'ecb';
            case self::MODE_CBC:
                return 'cbc';
            case self::MODE_CTR:
            case self::MODE_GCM:
                return 'ctr';
            case self::MODE_CFB:
                return 'cfb';
            case self::MODE_CFB8:
                return 'cfb8';
            case self::MODE_OFB:
                return 'ofb';
        }
    }

    /**
     * Pad "packets".
     *
     * Block ciphers working by encrypting between their specified [$this->]block_size at a time
     * If you ever need to encrypt or decrypt something that isn't of the proper length, it becomes necessary to
     * pad the input so that it is of the proper length.
     *
     * Padding is enabled by default.  Sometimes, however, it is undesirable to pad strings.  Such is the case in SSH,
     * where "packets" are padded with random bytes before being encrypted.  Unpad these packets and you risk stripping
     * away characters that shouldn't be stripped away. (SSH knows how many bytes are added because the length is
     * transmitted separately)
     *
     * @see self::disablePadding()
     * @access public
     */
    public function enablePadding()
    {
        $this->padding = true;
    }

    /**
     * Do not pad packets.
     *
     * @see self::enablePadding()
     * @access public
     */
    public function disablePadding()
    {
        $this->padding = false;
    }

    /**
     * Treat consecutive "packets" as if they are a continuous buffer.
     *
     * Say you have a 32-byte plaintext $plaintext.  Using the default behavior, the two following code snippets
     * will yield different outputs:
     *
     * <code>
     *    echo $rijndael->encrypt(substr($plaintext,  0, 16));
     *    echo $rijndael->encrypt(substr($plaintext, 16, 16));
     * </code>
     * <code>
     *    echo $rijndael->encrypt($plaintext);
     * </code>
     *
     * The solution is to enable the continuous buffer.  Although this will resolve the above discrepancy, it creates
     * another, as demonstrated with the following:
     *
     * <code>
     *    $rijndael->encrypt(substr($plaintext, 0, 16));
     *    echo $rijndael->decrypt($rijndael->encrypt(substr($plaintext, 16, 16)));
     * </code>
     * <code>
     *    echo $rijndael->decrypt($rijndael->encrypt(substr($plaintext, 16, 16)));
     * </code>
     *
     * With the continuous buffer disabled, these would yield the same output.  With it enabled, they yield different
     * outputs.  The reason is due to the fact that the initialization vector's change after every encryption /
     * decryption round when the continuous buffer is enabled.  When it's disabled, they remain constant.
     *
     * Put another way, when the continuous buffer is enabled, the state of the \phpseclib\Crypt\*() object changes after each
     * encryption / decryption round, whereas otherwise, it'd remain constant.  For this reason, it's recommended that
     * continuous buffers not be used.  They do offer better security and are, in fact, sometimes required (SSH uses them),
     * however, they are also less intuitive and more likely to cause you problems.
     *
     * @see self::disableContinuousBuffer()
     * @access public
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function enableContinuousBuffer()
    {
        if ($this->mode == self::MODE_ECB) {
            return;
        }

        if ($this->mode == self::MODE_GCM) {
            throw new \BadMethodCallException('This mode does not run in continuous mode');
        }

        $this->continuousBuffer = true;

        $this->setEngine();
    }

    /**
     * Treat consecutive packets as if they are a discontinuous buffer.
     *
     * The default behavior.
     *
     * @see self::enableContinuousBuffer()
     * @access public
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    public function disableContinuousBuffer()
    {
        if ($this->mode == self::MODE_ECB) {
            return;
        }
        if (!$this->continuousBuffer) {
            return;
        }

        $this->continuousBuffer = false;
        $this->changed = true;

        $this->setEngine();
    }

    /**
     * Test for engine validity
     *
     * @see self::__construct()
     * @param int $engine
     * @access private
     * @return bool
     */
    protected function isValidEngineHelper($engine)
    {
        switch ($engine) {
            case self::ENGINE_OPENSSL:
                $this->openssl_emulate_ctr = false;
                $result = $this->cipher_name_openssl &&
                          extension_loaded('openssl');
                if (!$result) {
                    return false;
                }

                $methods = openssl_get_cipher_methods();
                if (in_array($this->cipher_name_openssl, $methods)) {
                    return true;
                }
                // not all of openssl's symmetric cipher's support ctr. for those
                // that don't we'll emulate it
                switch ($this->mode) {
                    case self::MODE_CTR:
                        if (in_array(static::$cipher_name_openssl_ecb, $methods)) {
                            $this->openssl_emulate_ctr = true;
                            return true;
                        }
                }
                return false;
            case self::ENGINE_MCRYPT:
                return $this->cipher_name_mcrypt &&
                       extension_loaded('mcrypt') &&
                       in_array($this->cipher_name_mcrypt, @mcrypt_list_algorithms());
            case self::ENGINE_EVAL:
                return method_exists($this, 'setupInlineCrypt');
            case self::ENGINE_INTERNAL:
                return true;
        }

        return false;
    }

    /**
     * Test for engine validity
     *
     * @see self::__construct()
     * @param string $engine
     * @access public
     * @return bool
     */
    public function isValidEngine($engine)
    {
        static $reverseMap;
        if (!isset($reverseMap)) {
            $reverseMap = array_map('strtolower', self::ENGINE_MAP);
            $reverseMap = array_flip($reverseMap);
        }
        $engine = strtolower($engine);
        if (!isset($reverseMap[$engine])) {
            return false;
        }

        return $this->isValidEngineHelper($reverseMap[$engine]);
    }

    /**
     * Sets the preferred crypt engine
     *
     * Currently, $engine could be:
     *
     * - libsodium[very fast]
     *
     * - OpenSSL  [very fast]
     *
     * - mcrypt   [fast]
     *
     * - Eval     [slow]
     *
     * - PHP      [slowest]
     *
     * If the preferred crypt engine is not available the fastest available one will be used
     *
     * @see self::__construct()
     * @param string $engine
     * @access public
     */
    public function setPreferredEngine($engine)
    {
        static $reverseMap;
        if (!isset($reverseMap)) {
            $reverseMap = array_map('strtolower', self::ENGINE_MAP);
            $reverseMap = array_flip($reverseMap);
        }
        $engine = strtolower($engine);
        $this->preferredEngine = isset($reverseMap[$engine]) ? $reverseMap[$engine] : self::ENGINE_LIBSODIUM;

        $this->setEngine();
    }

    /**
     * Returns the engine currently being utilized
     *
     * @see self::setEngine()
     * @access public
     */
    public function getEngine()
    {
        return self::ENGINE_MAP[$this->engine];
    }

    /**
     * Sets the engine as appropriate
     *
     * @see self::__construct()
     * @access private
     */
    protected function setEngine()
    {
        $this->engine = null;

        $candidateEngines = [
            self::ENGINE_LIBSODIUM,
            self::ENGINE_OPENSSL_GCM,
            self::ENGINE_OPENSSL,
            self::ENGINE_MCRYPT,
            self::ENGINE_EVAL
        ];
        if (isset($this->preferredEngine)) {
            $temp = [$this->preferredEngine];
            $candidateEngines = array_merge(
                $temp,
                array_diff($candidateEngines, $temp)
            );
        }
        foreach ($candidateEngines as $engine) {
            if ($this->isValidEngineHelper($engine)) {
                $this->engine = $engine;
                break;
            }
        }
        if (!$this->engine) {
            $this->engine = self::ENGINE_INTERNAL;
        }

        if ($this->engine != self::ENGINE_MCRYPT && $this->enmcrypt) {
            // Closing the current mcrypt resource(s). _mcryptSetup() will, if needed,
            // (re)open them with the module named in $this->cipher_name_mcrypt
            @mcrypt_module_close($this->enmcrypt);
            @mcrypt_module_close($this->demcrypt);
            $this->enmcrypt = null;
            $this->demcrypt = null;

            if ($this->ecb) {
                @mcrypt_module_close($this->ecb);
                $this->ecb = null;
            }
        }

        $this->changed = true;
    }

    /**
     * Encrypts a block
     *
     * Note: Must be extended by the child \phpseclib\Crypt\* class
     *
     * @access private
     * @param string $in
     * @return string
     */
    abstract protected function encryptBlock($in);

    /**
     * Decrypts a block
     *
     * Note: Must be extended by the child \phpseclib\Crypt\* class
     *
     * @access private
     * @param string $in
     * @return string
     */
    abstract protected function decryptBlock($in);

    /**
     * Setup the key (expansion)
     *
     * Only used if $engine == self::ENGINE_INTERNAL
     *
     * Note: Must extend by the child \phpseclib\Crypt\* class
     *
     * @see self::setup()
     * @access private
     */
    abstract protected function setupKey();

    /**
     * Setup the self::ENGINE_INTERNAL $engine
     *
     * (re)init, if necessary, the internal cipher $engine and flush all $buffers
     * Used (only) if $engine == self::ENGINE_INTERNAL
     *
     * _setup() will be called each time if $changed === true
     * typically this happens when using one or more of following public methods:
     *
     * - setKey()
     *
     * - setIV()
     *
     * - disableContinuousBuffer()
     *
     * - First run of encrypt() / decrypt() with no init-settings
     *
     * @see self::setKey()
     * @see self::setIV()
     * @see self::disableContinuousBuffer()
     * @access private
     * @internal setup() is always called before en/decryption.
     * @internal Could, but not must, extend by the child Crypt_* class
     */
    protected function setup()
    {
        if (!$this->changed) {
            return;
        }

        $this->changed = false;

        if ($this->usePoly1305 && !isset($this->poly1305Key) && method_exists($this, 'createPoly1305Key')) {
            $this->createPoly1305Key();
        }

echo "SETTING BUFFER UP\n";
        $this->enbuffer = $this->debuffer = ['ciphertext' => '', 'xor' => '', 'pos' => 0, 'enmcrypt_init' => true];
        //$this->newtag = $this->oldtag = false;

        if ($this->usesNonce()) {
            if ($this->nonce === false) {
                throw new InsufficientSetupException('No nonce has been defined');
            }
            if ($this->mode == self::MODE_GCM && !in_array($this->engine, [self::ENGINE_LIBSODIUM, self::ENGINE_OPENSSL_GCM])) {
                $this->setupGCM();
            }
        } else {
            $this->iv = $this->origIV;
        }

        if ($this->iv === false && !in_array($this->mode, [self::MODE_STREAM, self::MODE_ECB])) {
            if ($this->mode != self::MODE_GCM || !in_array($this->engine, [self::ENGINE_LIBSODIUM, self::ENGINE_OPENSSL_GCM])) {
                throw new InsufficientSetupException('No IV has been defined');
            }
        }

        if ($this->key === false) {
            throw new InsufficientSetupException('No key has been defined');
        }

        $this->encryptIV = $this->decryptIV = $this->iv;

        switch ($this->engine) {
            case self::ENGINE_MCRYPT:
                $this->enchanged = $this->dechanged = true;

                if (!isset($this->enmcrypt)) {
                    static $mcrypt_modes = [
                        self::MODE_CTR    => 'ctr',
                        self::MODE_ECB    => MCRYPT_MODE_ECB,
                        self::MODE_CBC    => MCRYPT_MODE_CBC,
                        self::MODE_CFB    => 'ncfb',
                        self::MODE_CFB8   => MCRYPT_MODE_CFB,
                        self::MODE_OFB    => MCRYPT_MODE_NOFB,
                        self::MODE_STREAM => MCRYPT_MODE_STREAM,
                    ];

                    $this->demcrypt = @mcrypt_module_open($this->cipher_name_mcrypt, '', $mcrypt_modes[$this->mode], '');
                    $this->enmcrypt = @mcrypt_module_open($this->cipher_name_mcrypt, '', $mcrypt_modes[$this->mode], '');

                    // we need the $ecb mcrypt resource (only) in MODE_CFB with enableContinuousBuffer()
                    // to workaround mcrypt's broken ncfb implementation in buffered mode
                    // see: {@link http://phpseclib.sourceforge.net/cfb-demo.phps}
                    if ($this->mode == self::MODE_CFB) {
                        $this->ecb = @mcrypt_module_open($this->cipher_name_mcrypt, '', MCRYPT_MODE_ECB, '');
                    }
                } // else should mcrypt_generic_deinit be called?

                if ($this->mode == self::MODE_CFB) {
                    @mcrypt_generic_init($this->ecb, $this->key, str_repeat("\0", $this->block_size));
                }
                break;
            case self::ENGINE_INTERNAL:
                $this->setupKey();
                break;
            case self::ENGINE_EVAL:
                $this->setupKey();
                $this->setupInlineCrypt();
        }
    }

    /**
     * Pads a string
     *
     * Pads a string using the RSA PKCS padding standards so that its length is a multiple of the blocksize.
     * $this->block_size - (strlen($text) % $this->block_size) bytes are added, each of which is equal to
     * chr($this->block_size - (strlen($text) % $this->block_size)
     *
     * If padding is disabled and $text is not a multiple of the blocksize, the string will be padded regardless
     * and padding will, hence forth, be enabled.
     *
     * @see self::unpad()
     * @param string $text
     * @throws \LengthException if padding is disabled and the plaintext's length is not a multiple of the block size
     * @access private
     * @return string
     */
    protected function pad($text)
    {
        $length = strlen($text);

        if (!$this->padding) {
            if ($length % $this->block_size == 0) {
                return $text;
            } else {
                throw new \LengthException("The plaintext's length ($length) is not a multiple of the block size ({$this->block_size}). Try enabling padding.");
            }
        }

        $pad = $this->block_size - ($length % $this->block_size);

        return str_pad($text, $length + $pad, chr($pad));
    }

    /**
     * Unpads a string.
     *
     * If padding is enabled and the reported padding length is invalid the encryption key will be assumed to be wrong
     * and false will be returned.
     *
     * @see self::pad()
     * @param string $text
     * @throws \LengthException if the ciphertext's length is not a multiple of the block size
     * @access private
     * @return string
     */
    protected function unpad($text)
    {
        if (!$this->padding) {
            return $text;
        }

        $length = ord($text[strlen($text) - 1]);

        if (!$length || $length > $this->block_size) {
            throw new BadDecryptionException("The ciphertext has an invalid padding length ($length) compared to the block size ({$this->block_size})");
        }

        return substr($text, 0, -$length);
    }

    /**
     * Setup the performance-optimized function for de/encrypt()
     *
     * Stores the created (or existing) callback function-name
     * in $this->inline_crypt
     *
     * Internally for phpseclib developers:
     *
     *     _setupInlineCrypt() would be called only if:
     *
     *     - $this->engine === self::ENGINE_EVAL
     *
     *     - each time on _setup(), after(!) _setupKey()
     *
     *
     *     This ensures that _setupInlineCrypt() has always a
     *     full ready2go initializated internal cipher $engine state
     *     where, for example, the keys already expanded,
     *     keys/block_size calculated and such.
     *
     *     It is, each time if called, the responsibility of _setupInlineCrypt():
     *
     *     - to set $this->inline_crypt to a valid and fully working callback function
     *       as a (faster) replacement for encrypt() / decrypt()
     *
     *     - NOT to create unlimited callback functions (for memory reasons!)
     *       no matter how often _setupInlineCrypt() would be called. At some
     *       point of amount they must be generic re-useable.
     *
     *     - the code of _setupInlineCrypt() it self,
     *       and the generated callback code,
     *       must be, in following order:
     *       - 100% safe
     *       - 100% compatible to encrypt()/decrypt()
     *       - using only php5+ features/lang-constructs/php-extensions if
     *         compatibility (down to php4) or fallback is provided
     *       - readable/maintainable/understandable/commented and... not-cryptic-styled-code :-)
     *       - >= 10% faster than encrypt()/decrypt() [which is, by the way,
     *         the reason for the existence of _setupInlineCrypt() :-)]
     *       - memory-nice
     *       - short (as good as possible)
     *
     * Note: - _setupInlineCrypt() is using _createInlineCryptFunction() to create the full callback function code.
     *       - In case of using inline crypting, _setupInlineCrypt() must extend by the child \phpseclib\Crypt\* class.
     *       - The following variable names are reserved:
     *         - $_*  (all variable names prefixed with an underscore)
     *         - $self (object reference to it self. Do not use $this, but $self instead)
     *         - $in (the content of $in has to en/decrypt by the generated code)
     *       - The callback function should not use the 'return' statement, but en/decrypt'ing the content of $in only
     *
     *
     * @see self::setup()
     * @see self::createInlineCryptFunction()
     * @see self::encrypt()
     * @see self::decrypt()
     * @access private
     * @internal If a Crypt_* class providing inline crypting it must extend _setupInlineCrypt()
     */
    //protected function setupInlineCrypt();

    /**
     * Creates the performance-optimized function for en/decrypt()
     *
     * Internally for phpseclib developers:
     *
     *    _createInlineCryptFunction():
     *
     *    - merge the $cipher_code [setup'ed by _setupInlineCrypt()]
     *      with the current [$this->]mode of operation code
     *
     *    - create the $inline function, which called by encrypt() / decrypt()
     *      as its replacement to speed up the en/decryption operations.
     *
     *    - return the name of the created $inline callback function
     *
     *    - used to speed up en/decryption
     *
     *
     *
     *    The main reason why can speed up things [up to 50%] this way are:
     *
     *    - using variables more effective then regular.
     *      (ie no use of expensive arrays but integers $k_0, $k_1 ...
     *      or even, for example, the pure $key[] values hardcoded)
     *
     *    - avoiding 1000's of function calls of ie _encryptBlock()
     *      but inlining the crypt operations.
     *      in the mode of operation for() loop.
     *
     *    - full loop unroll the (sometimes key-dependent) rounds
     *      avoiding this way ++$i counters and runtime-if's etc...
     *
     *    The basic code architectur of the generated $inline en/decrypt()
     *    lambda function, in pseudo php, is:
     *
     *    <code>
     *    +----------------------------------------------------------------------------------------------+
     *    | callback $inline = create_function:                                                          |
     *    | lambda_function_0001_crypt_ECB($action, $text)                                               |
     *    | {                                                                                            |
     *    |     INSERT PHP CODE OF:                                                                      |
     *    |     $cipher_code['init_crypt'];                  // general init code.                       |
     *    |                                                  // ie: $sbox'es declarations used for       |
     *    |                                                  //     encrypt and decrypt'ing.             |
     *    |                                                                                              |
     *    |     switch ($action) {                                                                       |
     *    |         case 'encrypt':                                                                      |
     *    |             INSERT PHP CODE OF:                                                              |
     *    |             $cipher_code['init_encrypt'];       // encrypt sepcific init code.               |
     *    |                                                    ie: specified $key or $box                |
     *    |                                                        declarations for encrypt'ing.         |
     *    |                                                                                              |
     *    |             foreach ($ciphertext) {                                                          |
     *    |                 $in = $block_size of $ciphertext;                                            |
     *    |                                                                                              |
     *    |                 INSERT PHP CODE OF:                                                          |
     *    |                 $cipher_code['encrypt_block'];  // encrypt's (string) $in, which is always:  |
     *    |                                                 // strlen($in) == $this->block_size          |
     *    |                                                 // here comes the cipher algorithm in action |
     *    |                                                 // for encryption.                           |
     *    |                                                 // $cipher_code['encrypt_block'] has to      |
     *    |                                                 // encrypt the content of the $in variable   |
     *    |                                                                                              |
     *    |                 $plaintext .= $in;                                                           |
     *    |             }                                                                                |
     *    |             return $plaintext;                                                               |
     *    |                                                                                              |
     *    |         case 'decrypt':                                                                      |
     *    |             INSERT PHP CODE OF:                                                              |
     *    |             $cipher_code['init_decrypt'];       // decrypt sepcific init code                |
     *    |                                                    ie: specified $key or $box                |
     *    |                                                        declarations for decrypt'ing.         |
     *    |             foreach ($plaintext) {                                                           |
     *    |                 $in = $block_size of $plaintext;                                             |
     *    |                                                                                              |
     *    |                 INSERT PHP CODE OF:                                                          |
     *    |                 $cipher_code['decrypt_block'];  // decrypt's (string) $in, which is always   |
     *    |                                                 // strlen($in) == $this->block_size          |
     *    |                                                 // here comes the cipher algorithm in action |
     *    |                                                 // for decryption.                           |
     *    |                                                 // $cipher_code['decrypt_block'] has to      |
     *    |                                                 // decrypt the content of the $in variable   |
     *    |                 $ciphertext .= $in;                                                          |
     *    |             }                                                                                |
     *    |             return $ciphertext;                                                              |
     *    |     }                                                                                        |
     *    | }                                                                                            |
     *    +----------------------------------------------------------------------------------------------+
     *    </code>
     *
     *    See also the \phpseclib\Crypt\*::_setupInlineCrypt()'s for
     *    productive inline $cipher_code's how they works.
     *
     *    Structure of:
     *    <code>
     *    $cipher_code = [
     *        'init_crypt'    => (string) '', // optional
     *        'init_encrypt'  => (string) '', // optional
     *        'init_decrypt'  => (string) '', // optional
     *        'encrypt_block' => (string) '', // required
     *        'decrypt_block' => (string) ''  // required
     *    ];
     *    </code>
     *
     * @see self::setupInlineCrypt()
     * @see self::encrypt()
     * @see self::decrypt()
     * @param array $cipher_code
     * @access private
     * @return string (the name of the created callback function)
     */
    protected function createInlineCryptFunction($cipher_code)
    {
        $block_size = $this->block_size;

        // optional
        $init_crypt    = isset($cipher_code['init_crypt'])    ? $cipher_code['init_crypt']    : '';
        $init_encrypt  = isset($cipher_code['init_encrypt'])  ? $cipher_code['init_encrypt']  : '';
        $init_decrypt  = isset($cipher_code['init_decrypt'])  ? $cipher_code['init_decrypt']  : '';
        // required
        $encrypt_block = $cipher_code['encrypt_block'];
        $decrypt_block = $cipher_code['decrypt_block'];

        // Generating mode of operation inline code,
        // merged with the $cipher_code algorithm
        // for encrypt- and decryption.
        switch ($this->mode) {
            case self::MODE_CTR:
                $encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);
                    $_xor = $this->encryptIV;
                    $_buffer = &$this->enbuffer;
echo "\n\nthis far x2\n\n";
var_dump($_buffer);
                    if (strlen($_buffer["ciphertext"])) {
echo "\n\nthis far x1\n\n";
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["ciphertext"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                \phpseclib\Common\Functions\Strings::increment_str($_xor);
                                $_buffer["ciphertext"].= $in;
                            }
                            $_key = \phpseclib\Common\Functions\Strings::shift($_buffer["ciphertext"], '.$block_size.');
                            $_ciphertext.= $_block ^ $_key;
                        }
                    } else {
echo "\n\nthis far x3\n\n";
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            $in = $_xor;
                            '.$encrypt_block.'
                            \phpseclib\Common\Functions\Strings::increment_str($_xor);
                            $_key = $in;
                            $_ciphertext.= $_block ^ $_key;
                        }
                    }
echo "\n\nthis far x4\n\n";
                    if ($this->continuousBuffer) {
                        $this->encryptIV = $_xor;
                        if ($_start = $_plaintext_len % '.$block_size.') {
                            $_buffer["ciphertext"] = substr($_key, $_start) . $_buffer["ciphertext"];
                        }
                    }

                    return $_ciphertext;
                ';

                $decrypt = $init_encrypt . '
                    $_plaintext = "";
                    $_ciphertext_len = strlen($_text);
                    $_xor = $this->decryptIV;
                    $_buffer = &$this->debuffer;

                    if (strlen($_buffer["ciphertext"])) {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["ciphertext"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                \phpseclib\Common\Functions\Strings::increment_str($_xor);
                                $_buffer["ciphertext"].= $in;
                            }
                            $_key = \phpseclib\Common\Functions\Strings::shift($_buffer["ciphertext"], '.$block_size.');
                            $_plaintext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            $in = $_xor;
                            '.$encrypt_block.'
                            \phpseclib\Common\Functions\Strings::increment_str($_xor);
                            $_key = $in;
                            $_plaintext.= $_block ^ $_key;
                        }
                    }
                    if ($this->continuousBuffer) {
                        $this->decryptIV = $_xor;
                        if ($_start = $_ciphertext_len % '.$block_size.') {
                            $_buffer["ciphertext"] = substr($_key, $_start) . $_buffer["ciphertext"];
                        }
                    }

                    return $_plaintext;
                    ';
                break;

        }
/*
echo "\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
echo $encrypt;
echo "\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
exit;
*/
$zzz = '$func = function ($_action, $_text) { echo "\n\n\nbbbbbb\n\n\n"; ' . $init_crypt . 'if ($_action == "encrypt") { echo "\n\n\nzzzzz\n\n\n";' . $encrypt . ' echo "\n\n\naaaaaaa\n\n\n";} else { ' . $decrypt . ' }};';
/*
echo "\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
echo $zzz;
echo "\n\n\n\n\n\n\n\n\n\n\n\n\n\n";
exit;
*/

        // Before discrediting this, please read the following:
        // @see https://github.com/phpseclib/phpseclib/issues/1293
        // @see https://github.com/phpseclib/phpseclib/pull/1143
        eval($zzz);

        return \Closure::bind($func, $this, static::class);
    }

    /**
     * Convert float to int
     *
     * On ARM CPUs converting floats to ints doesn't always work
     *
     * @access private
     * @param string $x
     * @return int
     */
    protected static function safe_intval($x)
    {
        switch (true) {
            case is_int($x):
            // PHP 5.3, per http://php.net/releases/5_3_0.php, introduced "more consistent float rounding"
            case (php_uname('m') & "\xDF\xDF\xDF") != 'ARM':
                return $x;
        }
        return (fmod($x, 0x80000000) & 0x7FFFFFFF) |
            ((fmod(floor($x / 0x80000000), 2) & 1) << 31);
    }

    /**
     * eval()'able string for in-line float to int
     *
     * @access private
     * @return string
     */
    protected static function safe_intval_inline()
    {
        switch (true) {
            case defined('PHP_INT_SIZE') && PHP_INT_SIZE == 8:
            case (php_uname('m') & "\xDF\xDF\xDF") != 'ARM':
                return '%s';
                break;
            default:
                $safeint = '(is_int($temp = %s) ? $temp : (fmod($temp, 0x80000000) & 0x7FFFFFFF) | ';
                return $safeint . '((fmod(floor($temp / 0x80000000), 2) & 1) << 31))';
        }
    }

    /**
     * Sets up GCM parameters
     *
     * See steps 1-2 of https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-38d.pdf#page=23
     * for more info
     *
     * @access private
     */
    private function setupGCM()
    {
        // don't keep on re-calculating $this->h
        if (!$this->h || $this->h->key != $this->key) {
            $cipher = new static('ecb');
            $cipher->setKey($this->key);
            $cipher->disablePadding();

            $this->h = self::$gcmField->newInteger(
                Strings::switchEndianness($cipher->encrypt("\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0"))
            );
            $this->h->key = $this->key;
        }

        if (strlen($this->nonce) == 12) {
            $this->iv = $this->nonce . "\0\0\0\1";
        } else {
            $s = 16 * ceil(strlen($this->nonce) / 16) - strlen($this->nonce);
            $this->iv = $this->ghash(
                self::nullPad128($this->nonce) . str_repeat("\0", 8) . self::len64($this->nonce)
            );
        }
    }

    /**
     * Performs GHASH operation
     *
     * See https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-38d.pdf#page=20
     * for more info
     *
     * @see self::decrypt()
     * @see self::encrypt()
     * @access private
     * @param string $x
     * @return string
     */
    private function ghash($x)
    {
        $h = $this->h;
        $y = ["\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0"];
        $x = str_split($x, 16);
        $n = 0;
        // the switchEndianness calls are necessary because the multiplication algorithm in BinaryField/Integer
        // interprets strings as polynomials in big endian order whereas in GCM they're interpreted in little
        // endian order per https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-38d.pdf#page=19.
        // big endian order is what binary field elliptic curves use per http://www.secg.org/sec1-v2.pdf#page=18.

        // we could switchEndianness here instead of in the while loop but doing so in the while loop seems like it
        // might be slightly more performant
        //$x = Strings::switchEndianness($x);
        foreach ($x as $xn) {
            $xn = Strings::switchEndianness($xn);
            $t = $y[$n] ^ $xn;
            $temp = self::$gcmField->newInteger($t);
            $y[++$n] = $temp->multiply($h)->toBytes();
            $y[$n] = substr($y[$n], 1);
        }
        $y[$n] = Strings::switchEndianness($y[$n]);
        return $y[$n];
    }

    /**
     * Returns the bit length of a string in a packed format
     *
     * @see self::decrypt()
     * @see self::encrypt()
     * @see self::setupGCM()
     * @access private
     * @param string $str
     * @return string
     */
    private static function len64($str)
    {
        return "\0\0\0\0" . pack('N', 8 * strlen($str));
    }

    /**
     * NULL pads a string to be a multiple of 128
     *
     * @see self::decrypt()
     * @see self::encrypt()
     * @see self::setupGCM()
     * @access private
     * @param string $str
     * @return string
     */
    protected static function nullPad128($str)
    {
        $len = strlen($str);
        return $str . str_repeat("\0", 16 * ceil($len / 16) - $len);
    }

    /**
     * Calculates Poly1305 MAC
     *
     * On my system ChaCha20, with libsodium, takes 0.5s. With this custom Poly1305 implementation
     * it takes 1.2s.
     *
     * @see self::decrypt()
     * @see self::encrypt()
     * @access private
     * @param string $text
     * @return string
     */
    protected function poly1305($text)
    {
        $s = $this->poly1305Key; // strlen($this->poly1305Key) == 32
        $r = Strings::shift($s, 16);
        $r = strrev($r);
        $r&= "\x0f\xff\xff\xfc\x0f\xff\xff\xfc\x0f\xff\xff\xfc\x0f\xff\xff\xff";
        $s = strrev($s);

        $r = self::$poly1305Field->newInteger(new BigInteger($r, 256));
        $s = self::$poly1305Field->newInteger(new BigInteger($s, 256));
        $a = self::$poly1305Field->newInteger(new BigInteger());

        $blocks = str_split($text, 16);
        foreach ($blocks as $block) {
            $n = strrev($block . chr(1));
            $n = self::$poly1305Field->newInteger(new BigInteger($n, 256));
            $a = $a->add($n);
            $a = $a->multiply($r);
        }
        $r = $a->toBigInteger()->add($s->toBigInteger());
        $mask = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";
        return strrev($r->toBytes()) & $mask;
    }
}