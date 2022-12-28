<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:51
#


class ChangeColumnDefault extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->changeColumnDefault('test_schema.test_table_renamed', 'my_new_column_renamed', '???');
    }

    final public function down() {
        $this->changeColumnDefault('test_schema.test_table_renamed', 'my_new_column_renamed', '---???---');
    }
}

        
