<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.07.21
 * Time: 16:59
 */

namespace FlyCubePHP\Core\Database;

include_once 'BaseDatabaseAdapter.php';

class PostgreSQLAdapter extends BaseDatabaseAdapter
{
    public function __construct(array $settings) {
        parent::__construct($settings);
    }

    /**
     * Метод запроса списка таблиц базы данных
     * @return array|null
     * @throws
     */
    final public function tables()/*: array|null */{
        $res = $this->query("select * from information_schema.tables where table_schema != 'pg_catalog' and table_schema != 'information_schema' and table_type != 'VIEW';");
        if (!is_null($res)) {
            $tmpArr = array();
            foreach ($res as $item) {
                $tmpName = $item->table_schema . "." . $item->table_name;
                $tmpArr[] = $tmpName;
            }
            return $tmpArr;
        }
        return [];
    }

    /**
     * Имя коннектора
     * @return string
     */
    final public function name(): string {
        return "PostgreSQL";
    }

    /**
     * Метод создания строки с настройками подключения
     * @param array $settings - массив настроек
     * @return string
     */
    final protected function makeDSN(array $settings): string {
        if (empty($settings))
            return "";
        if (!array_key_exists('host', $settings))
            return "";
        $database = "";
        if (isset($settings['database']))
            $database = $settings['database'];

        $host = $settings['host'];
        $port = 5432;
        if (array_key_exists('port', $settings))
            $port = $settings['port'];
        $username = "";
        if (array_key_exists('username', $settings))
            $username = $settings['username'];
        $password = "";
        if (array_key_exists('password', $settings))
            $password = $settings['password'];

        if (!empty($database))
            return "pgsql:host=$host;port=$port;dbname=$database;user=$username;password=$password";

        return "pgsql:host=$host;port=$port;user=$username;password=$password";
    }
}