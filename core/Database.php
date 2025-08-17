<?php

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $config = Config::database();
        
        try {
            $this->connection = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database']
            );

            if ($this->connection->connect_error) {
                throw new Exception("Error de conexión: " . $this->connection->connect_error);
            }

            $this->connection->set_charset($config['charset']);
        } catch (Exception $e) {
            Logger::error("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener instancia singleton
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener conexión mysqli
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Ejecutar consulta preparada
     */
    public function query($sql, $params = [], $types = '')
    {
        $stmt = $this->connection->prepare($sql);
        
        if (!$stmt) {
            Logger::error("SQL Prepare failed: " . $this->connection->error);
            throw new Exception("Error en la consulta SQL");
        }

        if (!empty($params)) {
            if (empty($types)) {
                // Auto-detect types
                $types = str_repeat('s', count($params));
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types = substr_replace($types, 'i', 0, 1);
                    } elseif (is_float($param)) {
                        $types = substr_replace($types, 'd', 0, 1);
                    }
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        $result = $stmt->execute();
        
        if (!$result) {
            Logger::error("SQL Execute failed: " . $stmt->error);
            throw new Exception("Error al ejecutar la consulta");
        }

        return $stmt;
    }

    /**
     * Obtener una fila
     */
    public function fetchOne($sql, $params = [], $types = '')
    {
        $stmt = $this->query($sql, $params, $types);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /**
     * Obtener múltiples filas
     */
    public function fetchAll($sql, $params = [], $types = '')
    {
        $stmt = $this->query($sql, $params, $types);
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * Insertar registro y devolver ID
     */
    public function insert($sql, $params = [], $types = '')
    {
        $stmt = $this->query($sql, $params, $types);
        $insertId = $this->connection->insert_id;
        $stmt->close();
        return $insertId;
    }

    /**
     * Ejecutar operación (UPDATE, DELETE)
     */
    public function execute($sql, $params = [], $types = '')
    {
        $stmt = $this->query($sql, $params, $types);
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }

    /**
     * Iniciar transacción
     */
    public function beginTransaction()
    {
        $this->connection->autocommit(false);
        $this->connection->begin_transaction();
    }

    /**
     * Confirmar transacción
     */
    public function commit()
    {
        $this->connection->commit();
        $this->connection->autocommit(true);
    }

    /**
     * Revertir transacción
     */
    public function rollback()
    {
        $this->connection->rollback();
        $this->connection->autocommit(true);
    }

    /**
     * Escapar string para consultas
     */
    public function escape($string)
    {
        return $this->connection->real_escape_string($string);
    }

    /**
     * Cerrar conexión
     */
    public function close()
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}