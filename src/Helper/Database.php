<?php

namespace App\Helper;

class Database
{
    /**
     * @var \PDO
     */
    private $oDb;

    // --------------------------------------------------------------------------

    /**
     * Database constructor.
     *
     * @param string $sHost The Host to use for connecting to the database
     * @param string $sUser The User to use for connecting to the database
     * @param string $sPass The Pass to use for connecting to the database
     */
    public function __construct($sHost, $sUser, $sPass)
    {
        $this->oDb = new \PDO(
            'mysql:host=' . $sHost . ';',
            $sUser,
            $sPass
        );

        $this->oDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    // --------------------------------------------------------------------------

    /**
     * Short for queries
     *
     * @param string $sSql The query to execute
     *
     * @return \PDOStatement
     */
    public function query($sSql)
    {
        return $this->oDb->query($sSql);
    }

    // --------------------------------------------------------------------------

    /**
     * Expose the PDO API
     *
     * @return \PDO
     */
    public function api()
    {
        return $this->oDb;
    }
}
