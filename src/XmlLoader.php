<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use SimpleXMLElement;
use function extension_loaded;
use function libxml_clear_errors;
use function libxml_get_last_error;
use function libxml_use_internal_errors;
use function simplexml_load_file;

final class XmlLoader
{

    /**
     * @throws ErrorException
     */
    public function readXml(string $xmlFile): SimpleXMLElement
    {
        if (!extension_loaded('simplexml')) {
            throw new LogicException('In order to use xml coverage files, you need to enable the simplexml extension');
        }

        $libXmlErrorsOld = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlFile);

        if ($xml === false) {
            $libXmlError = libxml_get_last_error();
            $libXmlErrorMessage = $libXmlError === false ? '' : ' Error: ' . $libXmlError->message;
            throw new ErrorException("Failed to parse XML file: {$xmlFile}." . $libXmlErrorMessage);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($libXmlErrorsOld);

        return $xml;
    }

}
