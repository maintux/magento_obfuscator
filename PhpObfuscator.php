<?php
/**
 * PHP version ~5.5
 *
 * @todo De-Obfuscator class
 *
 * @category Component
 * @package  Fluency\Component\Obfuscator
 * @author   Rafael Ernesto Espinosa Santiesteban <ralphlnx@gmail.com>
 * @license  MIT <http://www.opensource.org/licenses/mit-license.php>
 * @link     http://fluency.inc.com
 */

namespace Fluency\Component\Obfuscator;

/**
 * Class PhpObfuscator
 * This class obfuscate/encodes the PHP code to that it becomes hard to read.
 * Inspired by Rochak Chauhan works.
 * Thanks to Javier González Rodríguez for helping me.
 * With higher ENCODING_LEVEL, code becomes harder to read. However the filesize and
 * execution time will increase as ENCODING_LEVEL goes higher.
 *
 * @category Component
 * @package  Fluency\Component\Obfuscator
 * @author   Rafael Ernesto Espinosa Santiesteban <ralphlnx@gmail.com>
 * @license  MIT <http://www.opensource.org/licenses/mit-license.php>
 * @link     http://fluency.inc.com
 */
class PhpObfuscator
{
    private $_fileName = "";
    private $_obfuscatedFileSuffix = "obf";
    private $_obfuscateFileName = "";
    private $_errors = array();
    private $_ciphers = array("rijndael-128", "rijndael-192", "rijndael-256");
    private $_key = '';

    const DEFAULT_ENCODING_LEVEL = 3;

    /**
     * Sets suffix for obfuscated files
     *
     * @param string $obfuscatedFileSuffix Suffix string
     *
     * @return $this
     */
    public function setObfuscatedFileSuffix($obfuscatedFileSuffix = 'obf')
    {
        $this->_obfuscatedFileSuffix = $obfuscatedFileSuffix;
        return $this;
    }

    /**
     * Obfuscates a file
     *
     * @param string $filePath Path to file
     *
     * @return bool|string
     */
    public function obfuscate($filePath)
    {
        if (trim($filePath) == "") {
            $this->_errors[] = "File Name cannot be blank in function: " .
                __FUNCTION__;
            return false;
        }
        if (!is_readable($filePath)) {
            $this->_errors[] = "Failed to open file: $filePath in the function: " .
                __FUNCTION__;
            return false;
        }

        $this->_fileName = trim($filePath);
        $name = explode(".", $this->_fileName);
        $ext = end($name);
        $pos = strrpos($this->_fileName, ".");

        $filePath = substr($this->_fileName, 0, $pos);

        $this->_obfuscateFileName = $obfuscateFileName = $filePath . "." .
            $this->_obfuscatedFileSuffix . "." . $ext;

        if (($fp = fopen($obfuscateFileName, "w+")) === false) {
            $this->_errors[] = "Failed to open file: $obfuscateFileName " .
                "for writing in the function: " . __FUNCTION__;
            return false;
        } else {

            fwrite($fp, "<?php \r\n");
            $line = php_strip_whitespace($this->_fileName);

            $line = $this->preProcessString($line);
            $line = $this->encodeString($line);
            $line .= "\r\n";
            fwrite($fp, $line);
        }

        fclose($fp);

        return $obfuscateFileName;
    }

    /**
     * Pre-process string
     *
     * @param string $str String to be parsed
     *
     * @return string
     */
    public function preProcessString($str)
    {
        $str = str_replace("<?php", "", $str);
        $str = str_replace("<?", "", $str);
        $str = str_replace("?>", "", $str);
        //$str = str_replace("'", "\x22", $str);

        $str = trim($str);

        return $str;
    }

    /**
     * Transform string into hex entities like \xnn
     *
     * @param string $string The string to be parsed
     *
     * @return string
     */
    private function _str2hex($string)
    {
        $hex = bin2hex($string);
        $xhex = "";

        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            $xhex .= '\x' . $hex[$i] . $hex[$i + 1];
        }

        return $xhex;
    }

    /**
     * Generates crypt key
     * @return string
     */
    private function _generateKey()
    {
        if ($this->_key == '') {
            $this->_key = pack(
                'H*',
                hash('SHA256', md5(uniqid()))
            );
        }

        return $this->_key;
    }

    /**
     * Encodes string using random algorithm for each level
     *
     * @param string $string String to be encoded
     * @param int    $level  Encryption level
     *
     * @return string
     */
    public function encodeString($string, $level = self::DEFAULT_ENCODING_LEVEL)
    {
        for ($i = 1; $i <= $level; $i++) {

            $cipher = $this->_ciphers[array_rand($this->_ciphers, 1)];

            $iv_size = mcrypt_get_iv_size($cipher, MCRYPT_MODE_CBC);
            $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
            $iv64 = base64_encode($iv);

            $key = $this->_generateKey();

            $crypt_string = mcrypt_encrypt(
                $cipher, $key, $string, MCRYPT_MODE_CBC, $iv
            );
            $current_string = base64_encode($crypt_string);

            $current_base = $this->_str2hex(
                "@eval(mcrypt_decrypt(\"{$cipher}\",\"".$key."\",base64_decode("
            );

            $current_string2 = 'eval("'. $current_base . $this->_str2hex('"') .
                $current_string . $this->_str2hex('"),') .
                $this->_str2hex("MCRYPT_MODE_CBC,base64_decode(\"{$iv64}\")));").
                '"' . ');';

            $string = $current_string2;
        }

        return $string;
    }

    /**
     * Function to return all encountered errors
     * @return array
     */
    public function getAllErrors()
    {
        return $this->_errors;
    }

    /**
     * Function to find if there were any errors
     *
     * @return boolean
     */
    public function hasErrors()
    {
        if (count($this->_errors) > 0) {
            return true;
        } else {
            return false;
        }
    }
}
