<?php

namespace IPay;

use IPay\Sadad\IPaySadad;
use IPay\Mellat\IPayMellat;
use IPay\Zarinpal\IPayZarinpal;
use IPay\Exceptions\RetryException;
use IPay\Exceptions\PortNotFoundException;
use IPay\Exceptions\InvalidRequestException;
use IPay\Exceptions\NotFoundTransactionException;

class IPay
{
    const P_MELLAT = 1;

    const P_SADAD = 2;

    const P_ZARINPAL = 3;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var DataBaseManager
     */
    protected $db;

    /**
     * Keep current port driver
     *
     * @var IPayMellat|IPaySadad|IPayZarinpal
     */
    protected $portClass;

    /**
     * Path of config file
     *
     * @var null|string
     */
    private $configFilePath = null;

    /**
     * @param null|string $port
     * @param null|string $configFile
     */
    public function __construct($port = null, $configFile = null)
    {
        $this->configFilePath = $configFile;

        $this->config = new Config($this->configFilePath);
        $this->db = new DataBaseManager($this->config);

        if (!is_null($this->config->get('timezone')))
            date_default_timezone_set($this->config->get('timezone'));

        if (!is_null($port)) $this->buildPort($port);
    }

    /**
     * Get supported ports
     *
     * @return array
     */
    public function getSupportedPorts()
    {
        return array(self::P_MELLAT, self::P_SADAD, self::P_ZARINPAL);
    }

    /**
     * Call methods of current driver
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->portClass, $name], $arguments);
    }

    /**
     * Callback
     *
     * @return $this->portClass
     *
     * @throws InvalidRequestException
     * @throws NotFoundTransactionException
     * @throws PortNotFoundException
     * @throws RetryException
     */
    public function verify()
    {
        if (!isset($_GET['transaction_id']))
            throw new InvalidRequestException;

        $transactionId = intval($_GET['transaction_id']);
        $transaction = $this->db->find($transactionId);

        if (!$transaction)
            throw new NotFoundTransactionException;

        if ($transaction->status == IPayAbstract::TRANSACTION_SUCCEED || $transaction->status == IPayAbstract::TRANSACTION_FAILED)
            throw new RetryException;

        $this->buildPort($transaction->port_id);

        return $this->portClass->verify($transaction);
    }

    /**
     * Create new object from port class
     *
     * @param int $port
     * @throws PortNotFoundException
     */
    private function buildPort($port)
    {
        switch ($port) {
            case self::P_MELLAT:
                $this->portClass = new IPayMellat($this->config, $this->db, self::P_MELLAT);
                break;

            case self::P_SADAD:
                $this->portClass = new IPaySadad($this->config, $this->db, self::P_SADAD);
                break;

            case self::P_ZARINPAL:
                $this->portClass = new IPayZarinpal($this->config, $this->db, self::P_ZARINPAL);
                break;

            default:
                throw new PortNotFoundException;
                break;
        }
    }
}
