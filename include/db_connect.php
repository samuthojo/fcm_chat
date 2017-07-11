<?php

/**
 * Handling database connection
 *
 */
class DbConnect {
 
    private $conn;
 
    function __construct() {        
    }
 
    /**
     * Establishing database connection
     * @return database connection handler
     */
    function connect() {
        include_once dirname(__FILE__) . '/config.php';
 
        // Connecting to mysql database
        //$this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        try {
            $this->conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USERNAME, DB_PASSWORD);
            // set the PDO error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            //echo "Connected successfully";
        } catch (PDOException $exc) {
            echo "Connection failed: " . $exc->getTraceAsString();
        }
 
        // returing connection resource
        return $this->conn;
    }
}

