<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:33
#


class AddColumn extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->addColumn('test_schema.test_table_renamed', 'my_new_column', [
            'if_not_exists' => true,
            'type' => 'text',
            'limit' => 128,
            'null' => false,
            'default' => '---'
        ]);
    }

    final public function down() {
        $this->dropColumn('test_schema.test_table_renamed', 'my_new_column');
    }
}

        
