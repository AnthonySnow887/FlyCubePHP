<?php
#
# Created by FlyCubeMigration generator.
# User: anton
# Date: 16.12.2022
# Time: 16:19
#


class CreateExtension extends \FlyCubePHP\Core\Migration\Migration
{
    final public function up() {
        $this->createExtension('uuid-ossp');
    }

    final public function down() {
        $this->dropExtension('uuid-ossp');
    }
}

        
