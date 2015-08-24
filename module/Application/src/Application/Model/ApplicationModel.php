<?php
namespace Application\Model;
use \Zend\Db\Adapter\Adapter;

/**
 * Class AbstractModel
 * Aggregates database connection adapter
 * @package Application\Model
 */
class ApplicationModel
{
    const API_URL = 'http://www.webservicex.net/CurrencyConvertor.asmx/ConversionRate?FromCurrency=%s&ToCurrency=%s';
    const TABLE_NAME = 'merchant_transactions';
    const NATIVE_CURRENCY = 'GBP';

    /**
     * @var CurrencyExchangeServiceInterface
     */
    protected $exchangeService;

    /**
     * @var $db Adapter
     */
    private $db;

    /**
     * @var \Zend\Cache\Storage\StorageInterface
     */
    protected $cache;
    /**
     * @var HttpClient
     */
    protected $client;

    /**
     * Exchange from currency
     * @var string currency code
     */
    protected $from;

    /**
     * Exchange to currency
     * @var string currency code
     */
    protected $to;


    public function __construct($cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * @param Adapter $db
     */
    public function setDB(Adapter $db)
    {
        $this->db = $db;
    }

    /**
     * @return Adapter
     */
    public function getDB()
    {
        return $this->db;
    }

    public function exchange($amount, $from = null, $to = null)
    {
        if ($from) {
            $this->setFrom($from);
        }
        if ($to) {
            $this->setTo($to);
        }

        $rate = $this->getRate();
        return $amount * $rate;
    }

    public function getRate($from = null, $to = null) {
        if ($from) {
            $this->setFrom($from);
        }
        if ($to) {
            $this->setTo($to);
        }

        if (empty($this->to)) {
            throw new \Exception('Exchange to currency was not set.');
        }

        if (empty($this->from)) {
            throw new \Exception('Exchange from currency was not set.');
        }

        if ($this->cache) {
            $item = $this->cache->getItem($this->get_key(), $success);
            if (!$success) {
                $item = $this->updateRate();
            }
        } else {
            $item = $this->updateRate();
        }

        return $item;
    }

    public function setTo($to)
    {
        $this->to = $to;
        $this->update_http_client_uri();
    }

    public function getTo()
    {
        return $this->to;
    }

    public function setFrom($from)
    {
        $this->from = $from;
        $this->update_http_client_uri();
    }

    public function getFrom(){
        return $this->from;
    }

    private function update_http_client_uri() {
        if (!$this->client instanceof HttpClient) {
            throw new \Exception('Please set your http client in the config file.');
        }
        $this->client->setUri(sprintf(self::API_URL, $this->from, $this->to));
    }

    public function setHttpClient($client)
    {
        $client->setOPtions(array(
            'adapter'      => 'Zend\Http\Client\Adapter\Curl'
        ));
        $this->client = $client;
    }

    public function getHttpClient()
    {
        return $this->client;
    }

    /**
     * Gets rates for currencies from gateway
     *
     * @throws \RuntimeException
     */
    protected function updateRate()
    {
        $response = $this->client->send();
        if ($response->getStatusCode() != 200) {
            throw new \Exception('Currency API gateway response failed.');
        }

        $xml = simplexml_load_string($response->getBody());
        if (!$xml) {
            throw new \Exception('Currency API response malformed.');
        }
        $rate = floatval($xml[0]);
        if ($this->cache) {
            $this->cache->setItem($this->get_key(), $rate);
        }
        return $rate;
    }

    /**
     * Gets cache key for current currency exchange
     * @return string
     */
    public function get_key()
    {
        return 'currencyExchange' . $this->from . '-' . $this->to;
    }

    /**
     * Gets full raport for merchant with given id
     * @param $merchantId
     * @return array|bool
     */
    public function getReport($merchantId)
    {
        $db = $this->getDB();
        $data = $db->query(
            sprintf(
                'SELECT "date", "value", "currency_code" FROM %s WHERE merchant_id = ?',
                self::TABLE_NAME
            ),
            array($merchantId)
        )->toArray();

        if (empty($data)) {
            return false;
        }


        $report = array();
        foreach ($data as $row) {
            $key = date('Y-m-d', $row['date']);
            if ($row['currency_code'] == self::NATIVE_CURRENCY) {
                $amount = $row['value'];
            } else {
                $amount = $this->exchangeService->exchange($row['value'], $row['currency_code'], self::NATIVE_CURRENCY);
            }

            if (array_key_exists($key, $report)) {
                $report[$key] += $amount;
            } else {
                $report[$key] = $amount;
            }
        }

        return $report;
    }

    /**
     * Sets currency exchange service
     *
     * @param $service
     */
    public function setCurrencyExchangeService($service)
    {
        $this->exchangeService = $service;
    }

    /**
     * @return CurrencyExchangeServiceInterface
     */
    public function getCurrencyExchangeService()
    {
        return $this->exchangeService;
    }

    /**
     * Imports data passed as array into local database
     *
     * @param array $data
     */
    public function importData($data) {
        $this->truncate_data();

        $db = $this->getDB();
        $formatter = numfmt_create('en_EN', \NumberFormatter::CURRENCY);

        foreach ($data as $row) {
            //extract currency code from amount
            $value = numfmt_parse_currency($formatter, $row['value'], $currencyCode);
            //convert dates into timestamps
            $ts = strtotime(str_replace('/', '-', $row['date']));
            //insert data
            $db->query(
                sprintf(
                    'INSERT INTO %s(\'id\', \'merchant_id\', \'date\', \'value\', \'currency_code\')
                             VALUES(NULL, ?, ?, ?, ?)',
                    self::TABLE_NAME
                ),
                array($row['merchant'], $ts, $value, $currencyCode)
            );
        }
    }

    /**
     * Clears database transactions table
     */
    public function truncate_data() {
        $db = $this->getDB();
        $db->query('DELETE FROM ' . self::TABLE_NAME . ' WHERE 1')->execute();
    }



}
