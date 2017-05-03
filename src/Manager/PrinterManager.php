<?php

namespace Smalot\Cups\Manager;

use Http\Client\HttpClient;
use Smalot\Cups\Model\Printer;
use Smalot\Cups\Model\PrinterInterface;
use Smalot\Cups\Transport\Response as CupsResponse;
use GuzzleHttp\Psr7\Request;

/**
 * Class Printer
 *
 * @package Smalot\Cups\Manager
 */
class PrinterManager extends ManagerAbstract
{

    /**
     * @var \Http\Client\HttpClient
     */
    protected $client;

    /**
     * Printer constructor.
     *
     * @param \Http\Client\HttpClient $client
     */
    public function __construct(HttpClient $client)
    {
        parent::__construct();

        $this->client = $client;

        $this->setCharset('us-ascii');
        $this->setLanguage('en-us');
        $this->setOperationId(0);
        $this->setUsername('');
    }

    /**
     * @param array $attributes
     *
     * @return \Smalot\Cups\Model\Printer[]
     */
    public function getList($attributes = [])
    {
        $request = $this->prepareGetListRequest($attributes);
        $response = $this->client->sendRequest($request);
        $result = CupsResponse::parseResponse($response);
        $values = $result->getValues();
        $list = [];

        if (!empty($values['printer-attributes'])) {
            foreach ($values['printer-attributes'] as $item) {
                $printer = new Printer();
                $this->fillAttributes($printer, $item);

                $list[] = $printer;
            }
        }

        return $list;
    }

    /**
     * @param \Smalot\Cups\Model\Printer $printer
     *
     * @return \Smalot\Cups\Model\Printer
     */
    public function reloadAttributes($printer)
    {
        $request = $this->prepareReloadAttributesRequest($printer->getUri());
        $response = $this->client->sendRequest($request);
        $result = CupsResponse::parseResponse($response);
        $values = $result->getValues();

        if (isset($values['printer-attributes'][0])) {
            $this->fillAttributes($printer, $values['printer-attributes'][0]);
        }

        return $printer;
    }

    /**
     * @return \Smalot\Cups\Model\Printer|null
     */
    public function getDefault()
    {
        $request = $this->prepareGetDefaultRequest(['all']);
        $response = $this->client->sendRequest($request);
        $result = CupsResponse::parseResponse($response);
        $values = $result->getValues();

        $printer = null;

        if (isset($values['printer-attributes'][0])) {
            $printer = new Printer();
            $this->fillAttributes($printer, $values['printer-attributes'][0]);
        }

        return $printer;
    }

    /**
     * @param \Smalot\Cups\Model\Printer $printer
     *
     * @return bool
     */
    public function pause(Printer $printer)
    {
        $request = $this->preparePauseRequest($printer->getUri());
        $response = $this->client->sendRequest($request);
        $result = CupsResponse::parseResponse($response);

        // Reload attributes to update printer status.
        $this->reloadAttributes($printer);

        return ($result->getStatusCode() == 'successfull-ok');
    }

    /**
     * @param \Smalot\Cups\Model\Printer $printer
     *
     * @return bool
     */
    public function resume(Printer $printer)
    {
        $request = $this->prepareResumeRequest($printer->getUri());
        $response = $this->client->sendRequest($request);
        $result = CupsResponse::parseResponse($response);

        // Reload attributes to update printer status.
        $this->reloadAttributes($printer);

        return ($result->getStatusCode() == 'successfull-ok');
    }

    /**
     * @param \Smalot\Cups\Model\Printer $printer
     *
     * @return bool
     */
    public function purge(Printer $printer)
    {
        $request = $this->preparePurgeRequest($printer->getUri());
        $response = $this->client->sendRequest($request);
        $result = CupsResponse::parseResponse($response);

        return ($result->getStatusCode() == 'successfull-ok');
    }

    /**
     * @param array $attributes
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function prepareGetListRequest($attributes = [])
    {
        $charset = $this->buildCharset();
        $language = $this->buildLanguage();
        $operationId = $this->buildOperationId();
        $metaAttributes = $this->buildPrinterRequestedAttributes($attributes);

        $content = chr(0x01).chr(0x01) // IPP version 1.1
          .chr(0x40).chr(0x02) // operation:  cups vendor extension: get printers
          .$operationId //           request-id
          .chr(0x01) // start operation-attributes | operation-attributes-tag
          .$charset
          .$language
          .$metaAttributes
          .chr(0x03);

        $headers = ['Content-Type' => 'application/ipp'];

        return new Request('POST', '/', $headers, $content);
    }

    /**
     * @param string $uri
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function prepareReloadAttributesRequest($uri)
    {
        $charset = $this->buildCharset();
        $language = $this->buildLanguage();
        $operationId = $this->buildOperationId();
        $username = $this->buildUsername();
        $printerAttributes = $this->buildPrinterAttributes();
        $printerUri = $this->buildPrinterURI($uri);

        $content = chr(0x01).chr(0x01) // 1.1  | version-number
          .chr(0x00).chr(0x0b) // Print-URI | operation-id
          .$operationId //           request-id
          .chr(0x01) // start operation-attributes | operation-attributes-tag
          .$charset
          .$language
          .$printerUri
          .$username
          .$printerAttributes
          .chr(0x03); // end-of-attributes | end-of-attributes-tag

        $headers = ['Content-Type' => 'application/ipp'];

        return new Request('POST', '/', $headers, $content);
    }

    /**
     * @param array $attributes
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function prepareGetDefaultRequest($attributes = [])
    {
        $charset = $this->buildCharset();
        $language = $this->buildLanguage();
        $operationId = $this->buildOperationId();
        $metaAttributes = $this->buildPrinterRequestedAttributes($attributes);

        $content = chr(0x01).chr(0x01) // IPP version 1.1
          .chr(0x40).chr(0x01) // operation:  cups vendor extension: get default printer
          .$operationId //           request-id
          .chr(0x01) // start operation-attributes | operation-attributes-tag
          .$charset
          .$language
          .$metaAttributes
          .chr(0x03);

        $headers = ['Content-Type' => 'application/ipp'];

        return new Request('POST', '/', $headers, $content);
    }

    /**
     * @param string $uri
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function preparePauseRequest($uri)
    {
        $charset = $this->buildCharset();
        $language = $this->buildLanguage();
        $operationId = $this->buildOperationId();
        $username = $this->buildUsername();
        $printerUri = $this->buildPrinterURI($uri);

        $content = chr(0x01).chr(0x01) // 1.1  | version-number
          .chr(0x00).chr(0x10) // Pause-Printer | operation-id
          .$operationId //           request-id
          .chr(0x01) // start operation-attributes | operation-attributes-tag
          .$charset
          .$language
          .$printerUri
          .$username
          .chr(0x03); // end-of-attributes | end-of-attributes-tag

        $headers = ['Content-Type' => 'application/ipp'];

        return new Request('POST', '/admin/', $headers, $content);
    }

    /**
     * @param string $uri
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function prepareResumeRequest($uri)
    {
        $charset = $this->buildCharset();
        $language = $this->buildLanguage();
        $operationId = $this->buildOperationId();
        $username = $this->buildUsername();
        $printerUri = $this->buildPrinterURI($uri);

        $content = chr(0x01).chr(0x01) // 1.1  | version-number
          .chr(0x00).chr(0x11) // Resume-Printer | operation-id
          .$operationId //           request-id
          .chr(0x01) // start operation-attributes | operation-attributes-tag
          .$charset
          .$language
          .$printerUri
          .$username
          .chr(0x03); // end-of-attributes | end-of-attributes-tag

        $headers = ['Content-Type' => 'application/ipp'];

        return new Request('POST', '/admin/', $headers, $content);
    }

    /**
     * @param string $uri
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    protected function preparePurgeRequest($uri)
    {
        $charset = $this->buildCharset();
        $language = $this->buildLanguage();
        $operationId = $this->buildOperationId();
        $username = $this->buildUsername();
        $printerUri = $this->buildPrinterURI($uri);

        // Needs a build function call.
        $message = '';

        $content = chr(0x01).chr(0x01) // 1.1  | version-number
          .chr(0x00).chr(0x12) // purge-Jobs | operation-id
          .$operationId //           request-id
          .chr(0x01) // start operation-attributes | operation-attributes-tag
          .$charset
          .$language
          .$printerUri
          .$username
          .chr(0x22)
          .$this->getStringLength('purge-jobs')
          .'purge-jobs'
          .$this->getStringLength(chr(0x01))
          .chr(0x01)
          .chr(0x03); // end-of-attributes | end-of-attributes-tag

        $headers = ['Content-Type' => 'application/ipp'];

        return new Request('POST', '/admin/', $headers, $content);
    }

    /**
     * @return array
     */
    protected function getDefaultAttributes()
    {
        return [
          'printer-uri-supported',
          'printer-name',
          'printer-state',
          'printer-location',
          'printer-info',
          'printer-type',
          'printer-icons',
        ];
    }

    /**
     * @param array $attributes
     *
     * @return string
     */
    protected function buildPrinterRequestedAttributes($attributes = [])
    {
        if (empty($attributes)) {
            $attributes = $this->getDefaultAttributes();
        }

        $metaAttributes = '';

        for ($i = 0; $i < count($attributes); $i++) {
            if ($i == 0) {
                $metaAttributes .= chr(0x44) // Keyword
                  .$this->getStringLength('requested-attributes')
                  .'requested-attributes'
                  .$this->getStringLength($attributes[0])
                  .$attributes[0];
            } else {
                $metaAttributes .= chr(0x44) // Keyword
                  .chr(0x0).chr(0x0) // zero-length name
                  .$this->getStringLength($attributes[$i])
                  .$attributes[$i];
            }
        }

        return $metaAttributes;
    }

    /**
     * @param \Smalot\Cups\Model\PrinterInterface $printer
     * @param $item
     *
     * @return \Smalot\Cups\Model\PrinterInterface
     */
    protected function fillAttributes(PrinterInterface $printer, $item)
    {
        $printer->setUri($item['printer-uri-supported'][0]);
        $printer->setName($item['printer-name'][0]);
        $printer->setStatus($item['printer-state'][0]);

        // Merge with attributes already set.
        $attributes = $printer->getAttributes();
        foreach ($item as $name => $value) {
            $attributes[$name] = $value;
        }
        $printer->setAttributes($attributes);

        return $printer;
    }
}
