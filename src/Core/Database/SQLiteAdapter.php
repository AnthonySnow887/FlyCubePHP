<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.07.21
 * Time: 16:56
 */

namespace FlyCubePHP\Core\Database;

include_once 'BaseDatabaseAdapter.php';

class SQLiteAdapter extends BaseDatabaseAdapter
{
    public function __construct(array $settings) {
        parent::__construct($settings);
    }

    /**
     * Метод запроса версии сервера базы данных
     * @return string
     */
    final public function serverVersion(): string {
        $res = $this->query("select sqlite_version() as version;");
        if (!empty($res))
            return $res[0]->version;
        return "";
    }

    /**
     * Метод запроса списка таблиц базы данных
     * @return array|null
     * @throws
     */
    final public function tables()/*: array|null */{
        $res = $this->query("SELECT tbl_name FROM sqlite_master WHERE type = 'table';");
        if (!is_null($res)) {
            $tmpArr = array();
            foreach ($res as $item)
                $tmpArr[] = $item->tbl_name;
            return $tmpArr;
        }
        return [];
    }

    /**
     * Имя коннектора
     * @return string
     */
    final public function name(): string {
        return "SQLite";
    }

    /**
     * Метод создания строки с настройками подключения
     * @param array $settings - массив настроек
     * @return string
     */
    final protected function makeDSN(array $settings): string {
        if (empty($settings))
            return "";
        $database = "";
        if (isset($settings['database']))
            $database = $settings['database'];
        return "sqlite:$database";
    }
}