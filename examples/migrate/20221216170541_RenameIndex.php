<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 17:05
#


class RenameIndex extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->renameIndex('test_schema.test_table_2', 'my_test_index', 'my_test_index_renamed');
    }

    final public function down() {
        $this->renameIndex('test_schema.test_table_2', 'my_test_index_renamed', 'my_test_index');
    }
}

        
