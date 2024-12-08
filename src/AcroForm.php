<?php

declare(strict_types=1);
/*
The MIT License (MIT)

Copyright (c) 2019 Jacques Archimède

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

namespace acroforms;

use acroforms\Model\FDFDocument;
use acroforms\Model\PDFDocument;
use acroforms\Parser\FDFParser;
use acroforms\Parser\PDFParser;
use acroforms\Utils\PDFtkBridge;
use acroforms\Utils\URLToolBox;
use acroforms\Writer\FDFWriter;
use acroforms\Writer\PDFWriter;

define('ACROFORM_VERSION', '1.0.6');

class AcroForm
{
    private $pdfDocument;
    private $fdfDocument;
    private $xrefManager;

    private $pdfSource = '';				//string: full pathname to the input pdf , a form file
    private $fdfSource = '';				//string: full pathname to the input fdf , a form data file

    private $support         = '';					//string set to 'native' for fpdm or 'pdftk' for pdf toolkit
    private $pdftkExecutable = 'pdftk';		// path to the 'pdftk' toolkit
    private $flattenMode     = false;			//if true, flatten field data as text and remove form fields (NOT YET SUPPORTED BY FPDM)
    private $compressMode    = false;			//boolean , pdftk feature only to compress streams
    private $uncompressMode  = false;		//boolean pdftk feature only to uncompress streams
    private $security        = [];					//Array holding securtity settings

    /**
     * Constructor
     *
     * @param string $pdf the pdf file name
     * @param array|string $options options keys : 'fdf', 'pdftk'
     */
    public function __construct($pdf, $options = [])
    {
        if (! is_array($options)) {
            throw new \Exception('AcroForm: Invalid instantiation of AcroForm, an array of options must be provided');
        }
        $options = array_merge([
            'fdf'   => '',
            'pdftk' => '',
        ], $options);
        if ($pdf == '') {
            throw new \Exception('AcroForm: Invalid instantiation of AcroForm, a pdf source must be provided');
        }
        $this->pdfSource = $pdf;
        if ($options['fdf'] != '') {
            $this->fdfSource = $options['fdf']; // Holds the data of the fields to fill the form
        }
        $this->support  = 'native';
        $this->security = [
            'password' => [
                'owner' => '',
                'user'  => '',
            ],
            'encrypt' => 0,
            'allow'   => [],
        ];
        $this->pdftkExecutable = $options['pdftk'];
        $this->pdfDocument     = new PDFDocument();
        $this->pdfDocument->load($this->pdfSource, $this->pdftkExecutable);
        $pdfParser = new PDFParser($this->pdfDocument);
        $pdfParser->parse();
        $this->xrefManager = $this->pdfDocument->getCrossReference()->getManager();
        $this->fdfDocument = new FDFDocument($this->pdfDocument);
        if ($this->fdfSource) {
            $this->fdfDocument->load($this->fdfSource);
            $fdfParser = new FDFParser($this->fdfDocument);
            $fdfParser->parse();
        }
    }

    /**
     * Loads a form data to be merged
     *
     * @param string|array $data a FDF file content or an array containing the values for the fields to change
     *
     * @return AcroForm this instance of AcroForm for chaining
     **/
    public function load($data)
    {
        if (is_array($data)) {
            $this->fdfDocument->setFormData($data);
        } elseif (is_string($data)) { // string with FDF data
            $this->fdfDocument->setContent($data);
            $fdfParser = new FDFParser($this->fdfDocument);
            $fdfParser->parse();
        } else {
            throw new \Exception('AcroForm: Invalid content type!');
        }
        return $this;
    }

    /**
     * Set a mode
     *
     * @param string $mode a choice between 'safe','check','halt
     * @param bool $value true/false flag
     *
     * @return AcroForm this instance of AcroForm for chaining
     **/
    private function setMode($mode, $value)
    {
        switch ($mode) {
            case 'safe':
                $this->xrefManager->setSafeMode($value);
                break;
            case 'check':
                $this->xrefManager->setCheckMode($value);
                break;
            case 'halt':
                $this->xrefManager->setHaltMode($value);
                break;
            case 'flatten':
                $this->flattenMode = $value;
                break;
            case 'compress_mode':
                $this->compressMode = $value;
                if ($value) {
                    $this->uncompressMode = false;
                }
                break;
            case 'uncompress_mode':
                $this->uncompressMode = $value;
                if ($value) {
                    $this->compressMode = false;
                }
                break;
            default:
                throw new \Exception(sprintf("AcroForm: setMode error, Invalid mode '%s'", $mode));
        }
        return $this;
    }

    /**
     * Retrieves informations from the pdf
     *
     * @return array
     **/
    public function info()
    {
        $info            = $this->pdfDocument->getMetadata();
        $info['Reader']  = ($this->support == 'native') ? 'Acroform ' . ACROFORM_VERSION : $this->support;
        $info['Fields']  = $this->fdfDocument->getFields();
        $info['Buttons'] = $this->fdfDocument->getButtons();
        $info['Modes']   = ['safe' => $this->xrefManager->isSafeMode() ? 'Yes' : 'No', 'check' => $this->xrefManager->isCheckMode() ? 'Yes' : 'No', 'flatten' =>($this->flattenMode) ? 'Yes' : 'No', 'compress_mode' => ($this->compressMode) ? 'Yes' : 'No', 'uncompress_mode' => ($this->uncompressMode) ? 'Yes' : 'No', 'halt' => $this->xrefManager->isHaltMode() ? 'Yes' : 'No'];
        return $info;
    }

    /**
     * Changes the support
     *
     * @param string $support Allow to use external support that has more advanced features (ie 'pdftk')
     *
     * @return AcroForm this instance of AcroForm for chaining
     **/
    public function setSupport($support)
    {
        $this->support = $support == 'pdftk' ? 'pdftk' : 'native';
        return $this;
    }

    /**
     *
     * Fixes a corrupted PDF file
     *
     * @return AcroForm this instance of AcroForm for chaining
     **/
    public function fix()
    {
        $this->setMode('check', true); // Compare the cross-reference table offsets with objects offsets in the pdf file
        $this->setMode('halt', false); // Do no stop on errors so fix is applied during merge process
        return $this;
    }

    /**
     *
     * Decides to use  the  compress filter to restore compression.
     *
     * @return AcroForm this instance of AcroForm for chaining
     *
     **/
    public function compress()
    {
        $this->setMode('compress', true);
        $this->setSupport('pdftk');
        return $this;
    }

    /**
     *
     * Decides to remove PDF page stream compression by applying the  uncompress  filter.
     *
     * @return AcroForm this instance of AcroForm for chaining
     *
     **/
    public function uncompress()
    {
        $this->setMode('uncompress', true);
        $this->setSupport('pdftk');
        return $this;
    }

    /**
     *
     * Activates the flatten output to remove form from pdf file keeping field datas.
     *
     * @return AcroForm this instance of AcroForm for chaining
     *
     **/
    public function flatten()
    {
        $this->setMode('flatten', true);
        $this->setSupport('pdftk');
        return $this;
    }

    /***
     *
     * Defines a password type
     *
     * @param string $type : 'owner' or  'user'
     * @param string $code : the password code
     *
     * @return \acroforms\AcroForm this instance of AcroForm for chaining
     **/
    public function password($type, $code)
    {
        $this->security['password']["{$type}"] = match ($type) {
            'owner', 'user' => $code,
            default => throw new \Exception(sprintf("AcroForm: Unsupported password type (%s), specify 'owner' or 'user' instead.", $type)),
        };
        $this->setSupport('pdftk');
        return $this;
    }

    /**
     *
     * Defines the encrytion to the given bits
     *
     * @param int $bits 0, 40 or 128
     *
     * @return AcroForm this instance of AcroForm for chaining
     **/
    public function encrypt($bits)
    {
        $this->security['encrypt'] = match ($bits) {
            0, 40, 128 => $bits,
            default => throw new \Exception(sprintf('AcroForm: unsupported encrypt value of %d, only 0, 40 and 128 are supported', $bits)),
        };
        $this->setSupport('pdftk');
        return $this;
    }

    /**
     * Allow permissions
     *
     * @param array $permissions If no permissions is given, returns help.
     *   Permissions  are applied to the output PDF only if an encryption
     *  strength is specified or an owner or user password is given.  If
     *  permissions  are	not  specified,  they default to 'none,' which
     *  means all of the following features are disabled.
     *
     *  The permissions section may include one or more of the following
     *  features:
     *
     *  Printing
     *    Top Quality Printing
     *
     * DegradedPrinting
     *    Lower Quality Printing
     *
     *  ModifyContents
     *     Also allows Assembly
     *
     *  Assembly
     *
     *  CopyContents
     *     Also allows ScreenReaders
     *
     *  ScreenReaders
     *
     *  ModifyAnnotations
     *     Also allows FillIn
     *
     *  FillIn
     *
     *  AllFeatures
     *     Allows  the  user	to  perform  all of the above, and top
     *     quality printing.
     *
     * @return array|AcroForm array of permission or this instance of AcroForm for chaining
     **/
    public function allow($permissions = null)
    {
        $permissionsHelp = ['Printing' => 'Top Quality Printing', 'DegradedPrinting' => 'Lower Quality Printing', 'ModifyContents' => 'Also allows Assembly', 'Assembly' => '', 'CopyContents' => 'Also allows ScreenReaders', 'ScreenReaders' => '', 'ModifyAnnotations' => 'Also allows FillIn', 'FillIn' => '', 'AllFeatures' => 'All above'];
        if (is_null($permissions)) {
            return $permissionsHelp;
        }
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        $perms                   = array_keys($permissionsHelp);
        $this->security['allow'] = array_intersect($permissions, $perms);
        $this->setSupport('pdftk');
        return $this;

    }

    /**
     *
     * Returns the fields that are text type
     *
     * @return array The fields that are text type
     **/
    public function getTextFields()
    {
        $fields    = [];
        $pdfFields = $this->pdfDocument->getFields();
        foreach ($pdfFields as $name => $field) {
            if ($field->isTextField() || $field->isChoice()) {
                $fields[] = $name;
            }
        }
        return $fields;
    }

    /**
     *
     * Returns the fields that are button type (checkboxes, radios, ...)
     *
     * @return array The fields that are button type
     **/
    public function getButtonFields()
    {
        $fields    = [];
        $pdfFields = $this->pdfDocument->getFields();
        foreach ($pdfFields as $name => $field) {
            if ($field->isButton()) {
                $fields[] = $name;
            }
        }
        return $fields;
    }

    /**
     *
     * Returns the field whose name is given as parameter
     *
     * @param string $fieldName The field name
     *
     * @return AcroField|null The AcroField object of the field or null if this field doesn't exists
     **/
    public function getField($fieldName)
    {
        return $this->pdfDocument->getField($fieldName);
    }

    /**
     *
     * Merge FDF file with a PDF file
     *
     * @param bool $flatten Optional, false by default, if true will use pdftk to flatten the pdf form
     *
     * @return AcroForm this instance of AcroForm for chaining
     **/
    public function merge($flatten = false)
    {
        if ($flatten) {
            $this->flatten();
        }
        if ($this->support == 'native') {
            $fieldsCount = count($this->pdfDocument->getFields());
            if ($fieldsCount) {
                $writer = new PDFWriter($this->pdfDocument);
                $fields = $this->fdfDocument->getFields();
                foreach ($fields as $name => $value) {
                    $writer->setFormFieldValue('current', $name, $value);
                }
                $buttons = $this->fdfDocument->getButtons();
                foreach ($buttons as $name => $value) {
                    $writer->setFormFieldValue('current', $name, $value);
                }
                $this->xrefManager->rebuildCrossReferenceTable();
                $this->xrefManager->updateCrossReferenceStart();
            } else {
                throw new \Exception('AcroForm: PDF file is empty!');
            }
        }
        return $this;
    }

    /**
     * Output PDF to some destination
     *
     * @param string $dest the destination
     * @param string $name the PDF filename
     **/
    public function output($dest = '', $name = '')
    {
        $pdfContent = '';
        if ($this->support == 'pdftk') {
            $tmpFile = false;
            $pdfFile = URLToolBox::resolvePath(URLToolBox::fixPath($this->pdfSource));
            if ($this->fdfSource) {
                $fdfFile = URLToolBox::resolvePath(URLToolBox::fixPath($this->fdfSource));
            } else {
                $pdfUrl = URLToolBox::getUrlfromDir($pdfFile);
                $temp   = tempnam(sys_get_temp_dir(), 'acroform_');
                if ($temp === false) {
                    throw new \Exception("AcroForm: output failed because it's impossible to create a temporary file");
                }
                $fdfFile = $temp . '.fdf';
                rename($temp, $fdfFile);
                $tmpFile = true;
                $writer  = new FDFWriter($this->fdfDocument);
                $ret     = $writer->output($pdfUrl, $fdfFile);
                if (!$ret['success']) {
                    throw new \Exception(sprintf('AcroForm: output failed as something goes wrong (Pdf was %s) during internal FDF generation of file %s, Reason : %s', $pdfUrl, $fdfFile, $ret['return']));
                }
            }
            $security = '';
            if ($this->security['password']['owner'] != '') {
                $security .= ' owner_pw "' . substr($this->security['password']['owner'], 0, 15) . '"';
            }
            if ($this->security['password']['user'] != '') {
                $security .= ' user_pw "' . substr($this->security['password']['user'], 0, 15) . '"';
            }
            if ($this->security['encrypt'] != 0) {
                $security .= ' encrypt_' . $this->security['encrypt'] . 'bit';
            }
            if (count($this->security['allow']) > 0) {
                $permissions = $this->security['allow'];
                $security .= ' allow ';
                foreach ($permissions as $permission) {
                    $security .= ' ' . $permission;
                }
            }
            $outputModes = '';
            if ($this->flattenMode) {
                $outputModes .= ' flatten';
            }
            if ($this->compressMode) {
                $outputModes .= ' compress';
            }
            if ($this->uncompressMode) {
                $outputModes .= ' uncompress';
            }
            $ret = PDFtkBridge::pdftk(
                $pdfFile,
                $fdfFile,
                [
                    'command'      => $this->pdftkExecutable,
                    'security'     => $security,
                    'output_modes' => $outputModes,
                ]
            );
            if ($tmpFile) {
                @unlink($fdfFile);
            }
            if ($ret['success']) {
                $pdf = new PDFDocument();
                $pdf->load($ret['output']);
                $pdfContent = $pdf->getContent();
                if ($tmpFile) {
                    @unlink($ret['output']);
                }
            } else {
                throw new \Exception(sprintf('AcroForm: %s', $ret['output']));
            }
        } else {
            $pdfContent = $this->pdfDocument->getBuffer();
        }
        $dest = strtoupper($dest);
        if ($dest == '') {
            $dest = $name == '' ? 'I' : 'F';
        }
        if ($name == '') {
            $name = 'doc.pdf';
        }
        switch ($dest) {
            case 'I':
                if (ob_get_length()) {
                    throw new \Exception('AcroForm: Some data has already been output, can\'t send PDF file');
                }
                if (php_sapi_name() != 'cli') {
                    header('Content-Type: application/pdf');
                    if (headers_sent()) {
                        throw new \Exception('AcroForm: Some data has already been output, can\'t send PDF file');
                    }
                    header('Content-Length: ' . strlen((string) $pdfContent));
                    header('Content-Disposition: inline; filename="' . $name . '"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $pdfContent;
                break;
            case 'D':
                if (ob_get_length()) {
                    throw new \Exception('AcroForm: Some data has already been output, can\'t send PDF file');
                }
                header('Content-Type: application/x-download');
                if (headers_sent()) {
                    throw new \Exception('AcroForm: Some data has already been output, can\'t send PDF file');
                }
                header('Content-Length: ' . strlen((string) $pdfContent));
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public,no-cache');
                echo $pdfContent;
                break;
            case 'F':
                $f = fopen($name, 'wb');
                if (!$f) {
                    throw new \Exception(sprintf('AcroForm: Unable to create output file: %s (currently opened under a PDF viewer?)', $name));
                }
                fwrite($f, (string) $pdfContent, strlen((string) $pdfContent));
                fclose($f);
                break;
            case 'S':
                return $pdfContent;
            default:
                throw new \Exception(sprintf('AcroForm: Incorrect output destination: %s', $dest));
        }
        return '';
    }

}
