<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:56
#


class ChangeColumnNull extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->changeColumnNull('test_schema.test_table_renamed', 'my_new_column_renamed');
    }

    final public function down() {
        $this->changeColumnNull('test_schema.test_table_renamed', 'my_new_column_renamed', true);
    }
}

        
