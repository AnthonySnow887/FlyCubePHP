<?php

namespace FlyCubePHP\Core\Database;

use FlyCubePHP\Core\Error\ErrorDatabase;

include_once 'BaseDatabaseAdapter.php';

class MySQLAdapter extends BaseDatabaseAdapter
{
    public function __construct(array $settings) {
        parent::__construct($settings);
    }

    /**
     * Метод запроса версии сервера базы данных
     * @return string
     */
    final public function serverVersion(): string {
        $res = $this->query("SHOW VARIABLES LIKE \"%version%\"");
        if (is_null($res))
            return "";
        foreach ($res as $r) {
            if (strcmp($r->Variable_name, 'version') !== 0)
                continue;
            preg_match('/^(\d+\.)?(\d+\.)?(\*|\d+)/', $r->Value, $matches, PREG_OFFSET_CAPTURE);
            if (!empty($matches))
                return $matches[0][0];

            return $r->Value;
        }
        return "";
    }

    /**
     * Метод запроса списка таблиц базы данных
     * @return array|null
     * @throws
     */
    final public function tables()/*: array|null */{
        $dbName = $this->database();
        $res = $this->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '$dbName';");
        if (!is_null($res)) {
            $tmpArr = array();
            foreach ($res as $item)
                $tmpArr[] = $item->table_name;
            return $tmpArr;
        }
        return [];
    }

    /**
     * Имя коннектора
     * @return string
     */
    final public function name(): string {
        return "MySQL";
    }

    /**
     * Получить корректное экранированное имя таблицы
     * @param string $name
     * @return string
     */
    final public function quoteTableName(string $name): string {
//        $nameLst = explode('.', $name);
//        $tmpName = "";
//        foreach ($nameLst as $n) {
//            if (empty($tmpName))
//                $tmpName = "`$n`";
//            else
//                $tmpName .= ".`$n`";
//        }
//        return $tmpName;
        return "`$name`";
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
            && !array_key_exists('unix_socket', $settings))
            return "";
        if (array_key_exists('host', $settings)
            && array_key_exists('unix_socket', $settings))
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => "Use only 'host' or 'unix_socket' in the config!",
                'class-name' => $this->objectName(),
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name()
            ]);

        $pdo = "mysql:";
        $database = "";
        if (isset($settings['database']))
            $database = $settings['database'];
        if (array_key_exists('host', $settings)) {
            $host = $settings['host'];
            $port = 3306;
            if (array_key_exists('port', $settings))
                $port = $settings['port'];
            $pdo .= "host=$host;port=$port";
        } else if (array_key_exists('unix_socket', $settings)) {
            $unixSocket = $settings['unix_socket'];
            $pdo .= "unix_socket=$unixSocket";
            ini_set('mysql.default_socket', $unixSocket);
            ini_set('pdo_mysql.default_socket', $unixSocket);
        }
        if (!empty($database))
            $pdo .= ";dbname=$database";

        return $pdo;
    }

    /**
     * Передавать логин и пароль в PDO при его создании как аргументы
     * @return bool
     */
    final protected function authSettingsTransferToPDO(): bool {
        return true;
    }
}