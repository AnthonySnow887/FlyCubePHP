<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:21
#


class CreateSchema extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->createSchema('test_schema');
    }

    final public function down() {
        $this->dropSchema('test_schema');
    }
}

        
