<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:59
#


class AddIndex extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->createTable('test_schema.test_table_2', [
            'id' => false,
            'if_not_exists' => true,
            'my_id' => [
                'type' => 'integer',
                'null' => false
            ],
            'my_id_2' => [
                'type' => 'integer',
                'null' => false
            ],
            'my_data' => [
                'type' => 'string',
                'limit' => 128,
                'default' => ''
            ]
        ]);
        $this->addIndex('test_schema.test_table_2', ['my_id'], ['name' => 'my_test_index', 'unique' => true]);
        $this->addIndex('test_schema.test_table_2', ['my_id_2']);
    }

    final public function down() {
        $this->dropTable('test_schema.test_table_2');
    }
}

        
