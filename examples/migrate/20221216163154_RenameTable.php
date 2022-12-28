<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:31
#


class RenameTable extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->renameTable('test_schema.test_table', 'test_schema.test_table_renamed');
    }

    final public function down() {
        $this->renameTable('test_schema.test_table_renamed', 'test_schema.test_table');
    }
}

        
