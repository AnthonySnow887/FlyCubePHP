<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 17:17
#


class AddForeignKeyPKey extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->createTable('test_schema.test_table_3', [
            'id' => false,
            'if_not_exists' => true,
            'my_id' => [
                'type' => 'integer',
                'null' => false
            ],
            'my_data' => [
                'type' => 'string',
                'limit' => 128,
                'default' => ''
            ]
        ]);
        $this->addForeignKeyPKey('test_schema.test_table_3', 'my_id',
                                   'test_schema.test_table_renamed',
                                   ['on_delete' => true, 'action' => 'CASCADE']);
    }

    final public function down() {
        $this->dropForeignKeyPKey('test_schema.test_table_3', 'my_id');
        $this->dropTable('test_schema.test_table_3');
    }
}

        
