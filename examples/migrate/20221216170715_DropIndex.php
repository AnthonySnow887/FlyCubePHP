<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 17:07
#


class DropIndex extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->dropIndex('test_schema.test_table_2', ['name' => 'my_test_index_renamed']);
        $this->dropIndex('test_schema.test_table_2', ['columns' => ['my_id_2']]);
    }

    final public function down() {
        $this->addIndex('test_schema.test_table_2', ['my_id'], ['name' => 'my_test_index_renamed', 'unique' => true]);
        $this->addIndex('test_schema.test_table_2', ['my_id_2']);
    }
}

        
