<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:37
#


class ChangeColumn extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->changeColumn('test_schema.test_table_renamed', 'my_new_column_renamed', 'varchar', [
            'limit' => 256,
            'null' => false,
            'default' => '---???---'
        ]);
    }

    final public function down() {
        $this->changeColumn('test_schema.test_table_renamed', 'my_new_column_renamed', 'text', [
            'limit' => 128,
            'null' => false,
            'default' => '---'
        ]);
    }
}

        
