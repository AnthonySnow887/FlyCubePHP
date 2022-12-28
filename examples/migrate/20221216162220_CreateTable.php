<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:22
#


class CreateTable extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->createTable('test_schema.test_table', [
                'id' => false,
                'if_not_exists' => true,
                'my_id' => [
                    'type' => 'integer',
                    'null' => false,
                    'primary_key' => true
                ],
                'my_data' => [
                    'type' => 'string',
                    'limit' => 128,
                    'default' => ''
                ]
            ]);
    }

    final public function down() {
        $this->dropTable('test_schema.test_table');
    }
}
        
