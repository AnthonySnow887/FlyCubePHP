<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 17:09
#


class SetPrimaryKey extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->setPrimaryKey('test_schema.test_table_2', 'my_id');
    }

    final public function down() {
        $this->dropPrimaryKey('test_schema.test_table_2', 'my_id');
    }
}

        
