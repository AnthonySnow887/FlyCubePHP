UPGRADE FROM 1.7.1 to 1.8.0
===========================

---
NOTE

After upgrading the project, you must clear the cache directory to avoid errors in client access to resource files.

Example command:
```bash
MyProject/bin> sudo ./fly_cube_php --clear-cache
```

---

Application config
------------------

* The following sections have been added (or changed) to the project configuration file ('config/application_env.conf').
  The absence of these sections (or their parameters) in the configuration file will tell the FlyCubePHP core to use the default values.

  ### Asset Pipeline Settings

  * Added parameters:
    * "FLY_CUBE_PHP_ENABLE_SCSS_LOGGING"

  #### Example of the entire configuration section

  ```
  #
  # --- asset pipeline settings ---
  #
  # Supported compression types:
  #  - gzip
  #  - deflate
  #
  # In production mode:
  #  - enable compression             - default true (enable/disable assets compression)
  #  - compression type               - default gzip (set assets compression type)
  #  - cache max age sec              - default 31536000 (set HTTP header Cache-Control max-age value)
  #  - prepare assets requires list   - default true (This flag allows you to 'insert' at the beginning of the list
  #                                                   of dependencies libraries located in 'lib/assets' and 'vendor/assets'.
  #                                                   The dependency tree does not break.)
  #  - enable scss logging            - default false (enable/disable scss logging for functions: @debug, @warn)
  #
  # In development mode:
  #  - enable compression             - default false (enable/disable assets compression)
  #  - compression type               - default gzip (set assets compression type)
  #  - cache max age sec              - default 31536000 (set HTTP header Cache-Control max-age value)
  #  - prepare assets requires list   - default true (This flag allows you to 'insert' at the beginning of the list
  #                                                   of dependencies libraries located in 'lib/assets' and 'vendor/assets'.
  #                                                   The dependency tree does not break.)
  #  - enable scss logging            - default true (enable/disable scss logging for functions: @debug, @warn)
  #
  #FLY_CUBE_PHP_ENABLE_ASSETS_COMPRESSION: true
  #FLY_CUBE_PHP_ASSETS_COMPRESSION_TYPE: gzip
  #FLY_CUBE_PHP_ASSETS_CACHE_MAX_AGE_SEC: 31536000
  #FLY_CUBE_PHP_PREPARE_ASSETS_REQUIRES_LIST: true
  #FLY_CUBE_PHP_ENABLE_SCSS_LOGGING: true
  ```

Database config
---------------

* Starting from version 1.8.0 it became possible to work with several databases at the same time. 
  To set up simultaneous access to several databases, you need to correctly set the contents of the "production_secondary" and "development_secondary" sections. 
  These fields tell the system kernel which settings sections to use for connections to additional databases and which adapters to create in a particular application mode. 
  The content of these sections is in a key-value format, where:
  * key - the abstract name of the database, to search for it in the system kernel during creation;
  * value - name of the section with connection settings.

  #### Example database config
  ```json
  {
    "default_postgresql_dev": {
      "adapter": "postgresql",
      "database": "photos_dev",
      "host": "127.0.01",
      "username": "postgres",
      "password": "postgres"
    },

    "default_postgresql_prod": {
      "adapter": "postgresql",
      "database": "photos_prod",
      "host": "127.0.01",
      "username": "postgres",
      "password": "postgres"
    },

    "postgresql_animals_dev": {
      "adapter": "postgresql",
      "database": "animals_dev",
      "host": "127.0.01",
      "username": "postgres",
      "password": "postgres"
    },

    "postgresql_animals_prod": {
      "adapter": "postgresql",
      "database": "animals_prod",
      "host": "127.0.01",
      "username": "postgres",
      "password": "postgres"
    },

    "production": "default_postgresql_prod",
    "development": "default_postgresql_dev",

    "production_secondary": {
      "animals": "postgresql_animals_prod"
    },

    "development_secondary": {
      "animals": "postgresql_animals_dev"
    }
  }
  ```

Working with multiple databases at the same time
------------------------------------------------

  Examples of working with multiple databases.

  ### Work directly with the DatabaseFactory:
  ```php
  use FlyCubePHP\Core\Database\DatabaseFactory;

  class AppCoreController extends ApplicationController
  {
      public function test() 
      {
          $primaryDB = DatabaseFactory::instance()->createDatabaseAdapter();
          if (is_null($primaryDB))
              return;
  
          $animalsDB = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => 'animals' ]);
          if (is_null($animalsDB))
              return;
  
          try {
              $res = $primaryDB->query("SELECT * FROM \"myTable\" LIMIT :f_value;", [ ":f_value" => 50 ]);
              $resAnimals = $animalsDB->query("SELECT * FROM \"myAnimalsTable\" LIMIT :f_value;", [ ":f_value" => 50 ]);
          } catch (ErrorDatabase $ex) {
              // Show error message.
              var_dump($ex->getMessage());
              return;
          }
          // Show result data.
          var_dump($res);
          var_dump($resAnimals);
      }
  }
  ```

  ### Working with data model ActiveRecord:
  ```php
  class Capybara extends \FlyCubePHP\Core\ActiveRecord\ActiveRecord
  {
      public function __construct() {
          parent::__construct(); // NOTE: always use to initialize the parent class!
          // Set database:
          $this->setDatabase('animals');
      }
  }
  ```

  ### Working with migrations:
  ```php
  class TestMigration extends \FlyCubePHP\Core\Migration\Migration
  {
      final public function configuration() {
          // Set database
          $this->setDatabase('animals');
      }
  }
  ```

DatabaseFactory
--------------

* Starting from version 1.8.0, in function createDatabaseAdapter arguments changed:

Before:
 ```php
 // 
 // Создать адаптер по работе с базой данных
 // @param bool $autoConnect - автоматически подключаться при создании
 // @return BaseDatabaseAdapter|null
 //
 // public function createDatabaseAdapter(bool $autoConnect = true) {...}
 // 

 // Get adapter with auto-connect
 DatabaseFactory::instance()->createDatabaseAdapter();
 // or
 DatabaseFactory::instance()->createDatabaseAdapter(true);
 
 // Get adapter without auto-connect
 DatabaseFactory::instance()->createDatabaseAdapter(false);
 ```

After:
 ```php
 //
 // Создать адаптер по работе с базой данных
 // @param array $args - массив параметров создания адаптера
 // @return BaseDatabaseAdapter|null
 //
 // ==== Args
 //
 // - [bool] auto-connect - connect automatically on creation (default: true)
 // - [string] database   - database key name in '*_secondary' config (default: '')
 //
 // NOTE: If database name is empty - used primary database.
 //
 // public function createDatabaseAdapter(array $args = [ 'auto-connect' => true ]) {...}
 //

 // Get adapter with auto-connect
 DatabaseFactory::instance()->createDatabaseAdapter();
 // or
 DatabaseFactory::instance()->createDatabaseAdapter([ 'auto-connect' => true ]);
 
 // Get adapter without auto-connect
 DatabaseFactory::instance()->createDatabaseAdapter([ 'auto-connect' => false ]);


 // --- Working with multiple databases ---
 
 // Get database specific adapter 
 DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => 'animals' ]);
 // Get database specific adapter & without auto-connect
 DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => 'animals', 'auto-connect' => false ]);
 ```

ActiveRecord
------------

* Starting from version 1.8.0, the ActiveRecord class has a method that allows you to set the name of the database key with which the migration will work.
  This name must be specified in the "*_secondary" section of the database configuration file.
  ```php
  /**
   * Название ключа базы данных, указанного в разделе конфигурационного файла «*_secondary», с которой будет работать модель
   * @return string
   *
   * NOTE: If database name is empty - used primary database.
   */
  final protected function database(): string {...}

  /**
   * Задать название ключа базы данных, указанного в разделе конфигурационного файла «*_secondary», с которой будет работать модель
   * @param string $database - имя базы данных для подключения
   *
   * NOTE: If database name is empty - used primary database.
   */
  final protected function setDatabase(string $database) {...}
  ```
  ### Example usage:
  ```php
  class Capybara extends \FlyCubePHP\Core\ActiveRecord\ActiveRecord
  {
      public function __construct() {
          parent::__construct(); // NOTE: always use to initialize the parent class!
          // Set database:
          $this->setDatabase('animals');
      }
  }
  ```


Migrations
----------

* Starting from version 1.8.0, the base class of migrations has an optional method ```configuration()``` containing instructions for setting up the migration 
  (for example: which database does the migration work with).
  ```php
  /**
   * Метод конфигурирования миграции
   */
  public function configuration();
  ```
  ### Example usage:
  ```php
  class TestMigration extends \FlyCubePHP\Core\Migration\Migration
  {
      final public function configuration() {
          // Set database
          $this->setDatabase('animals');
      }
  }
  ```

* Starting from version 1.8.0, the base class of migrations has a method that allows you to set the name of the database key with which the migration will work.
  This name must be specified in the "*_secondary" section of the database configuration file.
  ```php
  /**
   * Задать название используемой базы данных
   * @param string $database
   */
  final protected function setDatabase(string $database) {...}
  ```
  ### Example usage:
  ```php
  class TestMigration extends \FlyCubePHP\Core\Migration\Migration
  {
      final public function configuration() {
          // Set database
          $this->setDatabase('animals');
      }
  }
  ```
  
* Starting with version 1.8.0, the migrations base class has methods to add or remove database extensions.
  * Create extension:
    ```php
    /**
     * Подключить расширение базы данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_not_exists - добавить флаг 'IF NOT EXISTS'
     */
    final protected function createExtension(string $name, array $props = []) {...}
    ```
    ### Example usage:
    ```php
    class TestMigration extends \FlyCubePHP\Core\Migration\Migration
    {
        final public function up() {
            //
            // Create extension without check 'IF NOT EXISTS'
            //
            // If the extension "uuid-ossp" already exists, then an SQL error will be generated
            // and the installation of the migration will fail!
            //
            $this->createExtension('uuid-ossp');

            //
            // Create extension with check 'IF NOT EXISTS'
            //
            // If the extension "uuid-ossp" already exists, this step is ignored.
            //
            $this->createExtension('uuid-ossp', [ 'if_not_exists' => true ]);
        }

        final public function down() {
            ...
        }
    }
    ```

  * Drop extension:
    ```php
    /**
     * Удалить расширение базы данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_exists - добавить флаг 'IF EXISTS'
     */
    final protected function dropExtension(string $name, array $props = []) {...}
    ```
    ### Example usage:
    ```php
    class TestMigration extends \FlyCubePHP\Core\Migration\Migration
    {
        final public function up() {
            $this->createExtension('uuid-ossp');
        }

        final public function down() {
            //
            // Drop extension without check 'IF NOT EXISTS'
            //
            // If the extension "uuid-ossp" does not exist, then an SQL error will be generated
            // and the installation of the migration will fail!
            //
            $this->dropExtension('uuid-ossp');

            //
            // Drop extension with check 'IF NOT EXISTS'
            //
            // If the extension "uuid-ossp" does not exist, this step is ignored.
            //
            $this->dropExtension('uuid-ossp', [ 'if_exists' => true ]);
        }
    }
    ```
    
RouteCollector
--------------

* Add constraints to use automatic regular expression validation for the dynamic segment. 
  ### Example usage:
  ```php
  get('/photos/:id', [ 'to' => 'Photos#show', 'constraints' => [ 'id' => '/[A-Z]\d{5}/' ] ] );
  ```

* Add redirect to routes to use automatic redirect without calling a controller method.
  * HTTP status codes for GET: 301, 302, 303
  * HTTP status codes for POST / PUT / PATCH / DELETE: 307, 308

  > 
  > NOTE: 
  > substitution of input parameters into a new route is carried out by means of the expression "%{PARAM_NAME}" without spaces.
  > 

  ### Example usage:
  ```php
  // For GET:
  get("/stories", [ 'to' => redirect("/articles") ] );
  // --- or ---
  get("/stories/:name", [ 'to' => redirect("/articles/%{name}") ] );
  // --- or ---
  get("/stories/:name", [ 'to' => redirect("/articles/%{name}", /*status*/ 302) ] );
  
  // For POST:
  post('/test2', [ 'to' => redirect('/test1', /*status*/ 308) ]);
  // --or --
  post('/test2/:id', [ 'to' => redirect('/test1/%{id}', /*status*/ 308) ]);
  ```

* Add route globbing and wildcard segments.

  >
  > NOTE:
  > The segments prefixed with a star are called "wildcard segments" and can occur anywhere along the route.
  > 

  Route globbing is a way to specify that a particular parameter should be matched to all the remaining parts of a route. For example:
  ```php
  get('photos/*other', [ 'to' => 'Photos#unknown' ]);
  ```
  This route would match ```photos/12``` or ```/photos/long/path/to/12```, setting ```_params['other'] to "12" or "long/path/to/12"```.

  The segments prefixed with a star are called "wildcard segments". Wildcard segments can occur anywhere in a route. For example:
  ```php
  get('books/*section/:title', [ 'to' => 'Books#show' ]);
  ```
  would match ```books/some/section/last-words-a-memoir``` with ```_params['section'] equals 'some/section'```, and ```_params['title'] equals 'last-words-a-memoir'```.

  Technically, a route can have even more than one wildcard segment. The matcher assigns segments to parameters in an intuitive way. For example:
  ```php
  get('*a/foo/*b', [ 'to' => 'Test#index' ]);
  ```
  would match ```zoo/woo/foo/bar/baz``` with ```_params['a'] equals 'zoo/woo'```, and ```_params['b'] equals 'bar/baz'```.
