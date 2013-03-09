<?php
/**
 * Lingoes Converter
 *
 * @author windylea <www.windylea.com>
 * @copyright Copyright (c) 2013, WindyLea. All right reserved
 * @version 0.1
 */
class LingoesConverter
{
    /*
     * Path to a *.LD2/*.LDX dictionary file
     *
     * @access public
     * @var string
     */
    public $input;

    /*
     * (Optional) Path to the output file to be written to. If not specified, 
     * this value will be [Input file's name].txt
     *
     * @access public
     * @var string
     */
    public $output;

    /*
     * Log messages
     *
     * @access public
     * @var array
     */
    public $logs;

    /*
     * Encoding for the entry words in the dictionary. Currently the class 
     * itself can't determine the encoding of the dictionary so this property 
     * is needed. Default is "UTF-8"
     *
     * @access public
     * @var string
     */
    public $encodingWord = "UTF-8";

    /*
     * Encoding for the entry definitions in the dictionary. Default is "UTF-16LE"
     *
     * @access public
     * @var string
     */
    public $encodingDef = "UTF-16LE";

    /*
     * Input file's properties
     *
     * @access public
     * @var array
     */
    public $prop = array();

    /*
     * File handle for the input file
     *
     * @access protected
     * @var resources
     */
    protected $inputHandle;

    /*
     * File handle for the uncompressed data file
     *
     * @access protected
     * @var resources
     */
    protected $inflatedHandle;

    /*
     * Class destructor
     *
     * @access public
     */
    public function __destruct()
    {
        @fclose($this->inputHandle);
        @fclose($this->inflatedHandle);
    }

    /*
     * Checks if the selected encoding is valid or is supported
     *
     * @access public
     * @author windylea
     * @param string $input The encoding name to be checked
     * @param string $defaultValue If the input encoding is not found, it will 
        be replaced by this value
     * @return string Returns the correct encoding name
     */
    public function validateEncoding($input, $defaultValue)
    {
        if (!empty($input))
        {
            $encodingList = mb_list_encodings();
            $input = trim(strtolower($input));
            foreach ($encodingList as $encoding)
            {
                $test = strtolower($encoding);
                if ($test == $input)
                {
                    return $encoding;
                }
            }
        }

        return $defaultValue;
    }

    /*
     * Writes a message to the log
     *
     * @access public
     * @author windylea
     * @param string $message The log message
     * @return null
     */
    public function log($message)
    {
        $this->logs[] = array(time(), $message);
        return null;
    }

    /*
     * Parses file properties
     *
     * @access public
     * @author windylea
     * @return bool Returns TRUE on success, otherwise FALSE if an error occured
     */
    public function prop()
    {
        /*
         * Prepare the input file and get its information
         */
        $this->input = realpath($this->input);
        if (!file_exists($this->input) || !is_readable($this->input))
        {
            $this->log("Error: File does not exist or not readable!");
            return false;
        }

        $this->inputHandle = fopen($this->input, "r");

        /*
         * Gets version infomation by reading 2 bytes at offset 0x18 and 2 bytes
         * at offset 0x1A as unsigned shorts
         */
        fseek($this->inputHandle, 0x18);

        $major = current(unpack("S", fread($this->inputHandle, 2)));
        $minor = current(unpack("S", fread($this->inputHandle, 2)));

        $this->prop["dictVersion"] = $major . "." . $minor;

        /*
         * Gets dictionary ID by reading 16 bytes at offset 0x1C and convert 
         * them to hex string
         */
        fseek($this->inputHandle, 0x1C);
        $data = fread($this->inputHandle, 16);

        $this->prop["dictId"] = "";
        $chars = str_split($data);
        foreach($chars as $char)
        {
            $this->prop["dictId"] .= dechex(ord($char));
        }

        /*
         * Gets beginning offset for other offset information by reading 4
         * bytes at offset 0x5C as an integer and add 0x60 to this value
         */
        fseek($this->inputHandle, 0x5C);
        $data = current(unpack("S", fread($this->inputHandle, 4)));
        $this->prop["offsetStart"] = $data + 0x60;

        /*
         * Gets dictionary type by reading 4 bytes at the beginning offset as an
         * integer
         */
        fseek($this->inputHandle, $this->prop["offsetStart"]);
        $this->prop["dictType"] = current(unpack("S", fread($this->inputHandle, 4)));

        /*
         * Gets the end offset of the compressed data
         *
         * Gets information offset(?). On some dictionaries the beginning offset
         * equals to this information offset
         */
        fseek($this->inputHandle, $this->prop["offsetStart"] + 4);
        $data = current(unpack("I", fread($this->inputHandle, 4)));

        $this->prop["offsetInfo"] = $data + $this->prop["offsetStart"] + 0x0C;
        if($this->prop["dictType"] == 3)
        {
            /*
             * Just ignore it
             */
        } elseif(filesize($this->input) > ($this->prop["offsetInfo"] - 0x1C))
        {
            $this->prop["offsetStart"] = $this->prop["offsetInfo"];
        } else
        {
            $this->log("Error: Unsupported dictionary format");
            return false;
        }

        fseek($this->inputHandle, $this->prop["offsetStart"] + 4);
        $data = current(unpack("I", fread($this->inputHandle, 4)));
        $this->prop["offsetCompressedDataEnd"] = $data + $this->prop["offsetStart"] + 0x08;

        /*
         * Gets offset for the header of the compressed data
         */

        fseek($this->inputHandle, $this->prop["offsetStart"] + 8);
        $data = current(unpack("I", fread($this->inputHandle, 4)));
        $this->prop["offsetCompressedDataHeader"] = $data + $this->prop["offsetStart"] + 0x1C;

        fseek($this->inputHandle, $this->prop["offsetCompressedDataHeader"] + 0x08);
        $this->prop["offsetCompressedDataBegin"] = current(unpack("I", fread($this->inputHandle, 4)));

        /*
         * Gets offset of the dictionary words in the inflated file
         */
        fseek($this->inputHandle, $this->prop["offsetStart"] + 12);
        $this->prop["offsetWord"] = current(unpack("I", fread($this->inputHandle, 4)));

        /*
         * Gets total length of the words and offset of the dictionary XML 
         * strings in the inflated file
         */
        fseek($this->inputHandle, $this->prop["offsetStart"] + 16);
        $this->prop["lengthWord"] = current(unpack("I", fread($this->inputHandle, 4)));
        $this->prop["offsetXml"] = $this->prop["offsetWord"] + $this->prop["lengthWord"];

        /*
         * Gets total length of the XML definitions
         */
        fseek($this->inputHandle, $this->prop["offsetStart"] + 20);
        $this->prop["lengthXml"] = current(unpack("I", fread($this->inputHandle, 4)));

        ksort($this->prop);
        return true;
    }

    /*
     * Decompress gz-compressed data to file
     *
     * @access public
     * @author windylea
     * @return bool Returns TRUE on success, otherwise FALSE if an error occured
     */
    function unpack()
    {
        if (empty($this->prop))
        {
            $return = $this->prop();
            if (!$return)
            {
                return false;
            }
        }

        fseek($this->inputHandle, $this->prop["offsetCompressedDataHeader"] + 0x0C);
        $offsetList = array();

        $timeStart = microtime(true);
        $this->log("Message: Decompression started on " . @date(DATE_RFC1123, $timeStart));

        while($this->prop["offsetCompressedDataBegin"] + ftell($this->inputHandle) 
            <= $this->prop["offsetCompressedDataEnd"])
        {
            $data = fread($this->inputHandle, 4);
            if (strlen($data) == 4)
            {
                $offset = current(unpack("I", $data));
                if ($offset > 0)
                {
                    $offsetList[] = $offset;
                    $startOffset = ftell($this->inputHandle);
                } else
                {
                    break;
                }
            } else
            {
                break;
            }
        }

        $lastOffset = 0;
        $this->inflatedHandle = fopen($this->input . ".inflated", "w+");

        foreach ($offsetList as $offset)
        {
            fseek($this->inputHandle, $startOffset + $lastOffset);
            $data = fread($this->inputHandle, ($offset - $lastOffset));
            $uncompressed = @gzuncompress($data);

            if(!$uncompressed)
            {
                $this->log("Error: Decompression failed at offset 0x" . 
                    sprintf("%04x", ($startOffset + $lastOffset)) . " (tried to" . 
                    " uncompress " . ($offset - $lastOffset) . " bytes of data)");
                return false;
            } else
            {
                fwrite($this->inflatedHandle, $uncompressed);
            }

            $lastOffset = $offset;
        }

        $timeEnd = microtime(true);
        $this->log("Message: Decompression finished on " . @date(DATE_RFC1123, $timeStart) .
            " - Execution time: " . round(($timeEnd - $timeStart), 2) . " (s)");
        
        return true;
    }

    /*
     * Convert the uncompressed data stream to human-readable format
     *
     * @access public
     * @author windylea
     * @return bool Returns TRUE on success, otherwise FALSE if an error occured
     */
    function convert()
    {
        if (!$this->inflatedHandle)
        {
            $return = $this->unpack();
            if (!$return)
            {
                return false;
            }
        }

        if (empty($this->output))
        {
            $slashes = (strtoupper(substr(PHP_OS, 0, 3)) === "WIN") ? "\\" : "/";
            $pathInfo = pathinfo($this->input);
            $this->output = $pathInfo["dirname"] . $slashes . $pathInfo["filename"] . ".txt";
        }

        $this->encodingWord = self::validateEncoding($this->encodingWord, "UTF-8");
        $this->encodingDef = self::validateEncoding($this->encodingDef, "UTF-16LE");

        $timeStart = microtime(true);
        $this->log("Message: Conversion started on " . @date(DATE_RFC1123, $timeStart));
        $outputHandle = fopen($this->output, "w+");

        $dataLength = 10;
        $offsetWord = $this->prop["offsetWord"];
        $offsetXml = $this->prop["offsetXml"];
        $totalEntries = ($offsetWord / $dataLength) - 1;

        for ($i = 0; $i < $totalEntries; $i++)
        {
            fseek($this->inflatedHandle, $dataLength * $i);
            $lastWordOffset = fread($this->inflatedHandle, 4);

            if (strlen($lastWordOffset) == 4)
            {
                $lastWordOffset = current(unpack("I", $lastWordOffset));
                $lastXmlOffset = current(unpack("I", fread($this->inflatedHandle, 4)));
                $flags = ord(fread($this->inflatedHandle, 1)) & 0xff;
                $crossRefs = ord(fread($this->inflatedHandle, 1)) & 0xff;
                $currentWordOffset = current(unpack("I", fread($this->inflatedHandle, 4)));
                $currentXmlOffset = current(unpack("I", fread($this->inflatedHandle, 4)));

                if ($currentXmlOffset - $lastXmlOffset > 0)
                {
                    fseek($this->inflatedHandle, $offsetXml + $lastXmlOffset);
                    $xml = fread($this->inflatedHandle, ($currentXmlOffset - $lastXmlOffset));
                } else
                {
                    $xml = "";
                }

                for($j = $crossRefs; $j > 0; $j--)
                {
                    fseek($this->inflatedHandle, $offsetWord + $lastWordOffset);
                    $currentRef = current(unpack("I", fread($this->inflatedHandle, 4)));

                    fseek($this->inflatedHandle, $dataLength * $currentRef);
                    fseek($this->inflatedHandle, 4, SEEK_CUR);
                    $lastXmlOffset = current(unpack("I", fread($this->inflatedHandle, 4)));

                    fseek($this->inflatedHandle, 6, SEEK_CUR);
                    $currentXmlOffset = current(unpack("I", fread($this->inflatedHandle, 4)));

                    fseek($this->inflatedHandle,$offsetXml + $lastXmlOffset);
                    $xml .= fread($this->inflatedHandle, ($currentXmlOffset - $lastXmlOffset));

                    $lastWordOffset += 4;
                }

                $xml = @mb_convert_encoding($xml, "UTF-8", $this->encodingDef);
                if($currentWordOffset - $lastWordOffset <= 0)
                {
                    continue;
                }

                $leftPosition = strpos($xml, "<![CDATA[");
                $rightPosition = strpos($xml, "]]>");

                if (strpos($xml, "<![CDATA[") !== false)
                {
                    $leftPosition += strlen("<![CDATA[");
                    $length = abs($rightPosition - $leftPosition);
                    $position = ($rightPosition > $leftPosition) 
                        ? $leftPosition : $rightPosition;
                    $xml = substr($xml, $position, $length);

                    # Remove image tags
                    $xml = preg_replace("/<img .+?\/>/i", "", $xml);

                    # Dictionary cross-reference
                    $xml = str_replace('dict://key.[$DictID]/', "", $xml);                    
                } else
                {
                    /*
                     * Replace some of Lingoes's custom markup tags
                     */

                    # Remove self-closing tags except line break
                    $xml = preg_replace('/<[^>n]+?\/>/', '', $xml); 

                    # Text color
                    //$xml = str_replace('<x K="', '<font color="', $xml); 
                    //$xml = str_replace('</x>', '</font>', $xml);

                    # Dictionary cross-reference
                    //$xml = str_replace('<Y O="', '<a href="', $xml);
                    //$xml = str_replace('</Y>', '</a>', $xml);

                    # Font size
                    $xml = str_replace('<Ã>', '<span style="font-size:8pt;">', $xml);
                    $xml = str_replace('</Ã>', '</span>', $xml);

                    # Font size
                    $xml = str_replace('<Å>', '<span style="font-size:12pt;">', $xml); 
                    $xml = str_replace('</Å>', '</span>', $xml);

                    # Bold text
                    $xml = str_replace('<g>', '<strong>', $xml);
                    $xml = str_replace('</g>', '</strong>', $xml);

                    # Styling elements
                    //$xml = str_replace('<Í P="', '<span style="', $xml);
                    //$xml = str_replace('</Í>', '</span>', $xml);

                    # Special text color
                    $xml = str_replace('<U>', '<span style="color:#c00000">', $xml);
                    $xml = str_replace('</U>', '</span>', $xml);

                    # Special text color
                    $xml = str_replace('<M>', '<span style="color:#009900">', $xml);
                    $xml = str_replace('</M>', '</span>', $xml);

                    # Unordered list elements
                    $xml = preg_replace('/<ï>/', '<ul><li>', $xml, 1);
                    $xml = preg_replace('/<\/ï>(?!.*<\/ï>)/', '</li></ul>', $xml, 1);
                    $xml = str_replace('<ï>', '<li>', $xml); 
                    $xml = str_replace('</ï>', '</li>', $xml);

                    # Italic text
                    $xml = str_replace('<h>', '<em>', $xml);
                    $xml = str_replace('</h>', '</em>', $xml);

                    # Line break
                    $xml = str_replace('<n />', '<br />', $xml); 
                }

                # Escape slashes
                $xml = str_replace("\\", "\\\\", $xml);

                fseek($this->inflatedHandle, $offsetWord + $lastWordOffset);
                $word = fread($this->inflatedHandle, ($currentWordOffset - $lastWordOffset));
                $word = @mb_convert_encoding($word, "UTF-8", $this->encodingWord);

                fwrite($outputHandle, $word . "\t" . $xml . "\r\n");
            } else
            {
                break;
            }
        }

        fclose($this->inflatedHandle);
        fclose($this->inputHandle);
        @unlink($this->input . ".inflated");

        $timeEnd = microtime(true);
        $this->log("Message: Conversion finished on " . @date(DATE_RFC1123, $timeStart) .
            " - Execution time: " . round(($timeEnd - $timeStart), 2) . " (s)");
        return true;
    }
}
?>