<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:36
#


class RenameColumn extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->renameColumn('test_schema.test_table_renamed', 'my_new_column', 'my_new_column_renamed');
    }

    final public function down() {
        $this->renameColumn('test_schema.test_table_renamed', 'my_new_column_renamed', 'my_new_column');
    }
}

        
