<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 11.08.21
 * Time: 18:50
 */

namespace FlyCubePHP\Core\Migration;

include_once 'Migration.php';

abstract class BaseSchema extends Migration
{
    public function __construct(int $version, string $database) {
        parent::__construct($version);
        $this->setDatabase($database);
    }

    final public function down() {
    }
}