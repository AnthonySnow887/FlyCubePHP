<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 17:11
#


class AddForeignKey extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->addForeignKey('test_schema.test_table_2', ['my_id_2'],
                             'test_schema.test_table_renamed', ['my_id'],
                             ['on_delete' => true, 'action' => 'CASCADE']);
    }

    final public function down() {
        $this->dropForeignKey('test_schema.test_table_2', ['my_id_2']);
    }
}

        
