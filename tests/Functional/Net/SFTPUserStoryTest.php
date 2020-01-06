<?php

/**
 * @author    Andreas Fischer <bantu@phpbb.com>
 * @copyright 2014 Andreas Fischer
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 */

use phpseclib3\Net\SFTP;

class Functional_Net_SFTPUserStoryTest extends PhpseclibFunctionalTestCase
{
    static protected $scratchDir;
    static protected $exampleData;
    static protected $exampleDataLength;
    static protected $buffer;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$scratchDir = uniqid('phpseclib-sftp-scratch-');

        self::$exampleData = str_repeat('abcde12345', 1000);
        self::$exampleDataLength = 10000;
    }


    public function testPasswordLogin()
    {
	    define('NET_SSH2_LOGGING', 2);
	    while (true) {
		    $sftp = new SFTP($this->getEnv('SSH_HOSTNAME'));
                    $username = $this->getEnv('SSH_USERNAME');
		    $password = $this->getEnv('SSH_PASSWORD');
		    try {
			    $sftp->login($username, $password);
		    } catch (\Exception $e) {
			    echo $e->getMessage() . "\n\n";
			    echo $sftp->getLog() . "\n\n";
			    print_r($sftp->getAlgorithmsNegotiated());
echo "ciphertext = " . bin2hex($sftp->zzz) . "\n";
echo "nonce = " . bin2hex($sftp->zzznonce) . "\n";
echo "aad = " . bin2hex($sftp->zzzaad) . "\n";
echo "tag = " . bin2hex($sftp->zzztag) . "\n";
echo "key = " . bin2hex($sftp->zzzkey) . "\n";
			    exit;
		    }
	    }

        return $sftp;
    }

}
