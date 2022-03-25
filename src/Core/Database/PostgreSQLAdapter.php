<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.07.21
 * Time: 16:59
 */

namespace FlyCubePHP\Core\Database;

use FlyCubePHP\Core\Error\ErrorDatabase;

include_once 'BaseDatabaseAdapter.php';

class PostgreSQLAdapter extends BaseDatabaseAdapter
{
    public function __construct(array $settings) {
        parent::__construct($settings);
    }

    /**
     * Метод запроса версии сервера базы данных
     * @return string
     */
    final public function serverVersion(): string {
        $res = $this->query("SHOW server_version;");
        if (!empty($res))
            return $res[0]->server_version;
        return "";
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
     * Получить корректное экранированное имя таблицы
     * @param string $name
     * @return string
     */
    final public function quoteTableName(string $name): string {
        $nameLst = explode('.', $name);
        $tmpName = "";
        foreach ($nameLst as $n) {
            if (empty($tmpName))
                $tmpName = "\"$n\"";
            else
                $tmpName .= ".\"$n\"";
        }
        return $tmpName;
    }

    /**
     * Метод создания строки с настройками подключения
     * @param array $settings - массив настроек
     * @return string
     * @throws
     */
    final protected function makeDSN(array $settings): string {
        if (empty($settings))
            return "";
        if (!array_key_exists('host', $settings)
            && !array_key_exists('unix_socket_dir', $settings))
            return "";
        if (array_key_exists('host', $settings)
            && array_key_exists('unix_socket_dir', $settings))
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => "Use only 'host' or 'unix_socket_dir' in the config!",
                'class-name' => $this->objectName(),
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name()
            ]);

        $pdo = "pgsql:";
        $database = "";
        if (isset($settings['database']))
            $database = $settings['database'];

        if (array_key_exists('host', $settings)) {
            $host = $settings['host'];
            $port = 5432;
            if (array_key_exists('port', $settings))
                $port = $settings['port'];
            $pdo .= "host=$host;port=$port";
        } else if (array_key_exists('unix_socket_dir', $settings)) {
            $unixSocket = $settings['unix_socket_dir'];
            $pdo .= "host=$unixSocket";
        }
        if (!empty($database))
            $pdo .= ";dbname=$database";

        $username = "";
        if (array_key_exists('username', $settings))
            $username = $settings['username'];
        $password = "";
        if (array_key_exists('password', $settings))
            $password = $settings['password'];

        $pdo .= ";user=$username;password=$password";
        return $pdo;
    }
}