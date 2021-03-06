<?php

/*
FlyCubePHP WebSockets based on the code and idea described in morozovsk/websocket.
https://github.com/morozovsk/websocket
Released under the MIT license
*/

namespace FlyCubePHP\WebSockets\Server;

include_once __DIR__.'/../../FlyCubePHPVersion.php';
include_once __DIR__.'/../../FlyCubePHPAutoLoader.php';
include_once __DIR__.'/../../FlyCubePHPErrorHandling.php';
include_once __DIR__.'/../../FlyCubePHPEnvLoader.php';
include_once __DIR__.'/../../Core/Logger/Logger.php';
include_once __DIR__.'/../../Core/Config/Config.php';
include_once __DIR__.'/../../Core/ActiveRecord/ActiveRecord.php';
include_once __DIR__.'/../../Core/Session/Session.php';
include_once __DIR__.'/../../Core/Cookie/Cookie.php';
include_once __DIR__.'/../../Core/Routes/RouteCollector.php';
include_once __DIR__.'/../../Core/Database/DatabaseFactory.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
//include_once __DIR__.'/../../ComponentsCore/ComponentsManager.php'; // TODO to add or not?
include_once __DIR__.'/../Config/WSConfig.php';
include_once __DIR__.'/../ActionCable/BaseChannel.php';

include_once 'WSWorker.php';
include_once 'Adapters/IPCServerAdapter.php';
include_once 'Adapters/RedisServerAdapter.php';


use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Database\DatabaseFactory;
use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\WebSockets\Config\WSConfig;
use FlyCubePHP\WebSockets\Server\Adapters\IPCServerAdapter;
use FlyCubePHP\WebSockets\Server\Adapters\RedisServerAdapter;


class WSServer
{
    private $_host;
    private $_port;
    private $_workersNum = 1;
    private $_mountPath = "/cable";
    private $_isEnabledPerform = true;
    private $_workersControls = array();
    private $_pid;

    function __construct()
    {
        $this->_host = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_WS_SERVER_HOST, "127.0.0.1");
        $this->_port = intval(WSConfig::instance()->currentSettingsValue(WSConfig::TAG_WS_SERVER_PORT, 8000));
        $this->_workersNum = intval(WSConfig::instance()->currentSettingsValue(WSConfig::TAG_WS_SERVER_WORKERS_NUM, 1));
        $this->_mountPath = Config::instance()->arg(Config::TAG_ACTION_CABLE_MOUNT_PATH, "/cable");
        $this->_isEnabledPerform = CoreHelper::toBool(Config::instance()->arg(Config::TAG_ACTION_CABLE_ENABLE_PERFORM, true));
        if ($this->_workersNum <= 0) {
            $errMsg = "[". self::class ."] Invalid WS Workers number (num <= 0)!";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $this->_pid = posix_getpid();
    }

    /**
     * ?????????? ?????????????? ???????????????????????? ???????????? WebSockets ??????????????
     * @throws \FlyCubePHP\Core\Error\Error
     */
    public function start()
    {
        // --- set 'SERVER_ADDR' & 'HTTP_HOST ---
        $_SERVER['SERVER_ADDR'] = $this->_host;
        $_SERVER['HTTP_HOST'] = $this->_host;

        // --- show info ---
        $infoMsg = "[". self::class ."] Start WSServer. PID: " . $this->_pid;
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        $infoMsg = "[". self::class ."] App path: " . CoreHelper::rootDir();
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        $infoMsg = "[". self::class ."] Action Cable mount path: " . $this->_mountPath;
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        $infoMsg = "[". self::class ."] Enable perform: " . CoreHelper::boolToStr($this->_isEnabledPerform);
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        // --- open server socket ---
        $host = $this->_host;
        $port = $this->_port;
        $server = stream_socket_server("tcp://$host:$port", $errorNumber, $errorString);
        stream_set_blocking($server, 0);
        if (!$server) {
            $errMsg = "[". self::class ."] stream_socket_server: $errorString ($errorNumber)";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $infoMsg = "[". self::class ."] Listen on: tcp://$host:$port";
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        // --- include env file ---
        $env_devel = CoreHelper::buildPath(CoreHelper::rootDir(), "config", "environments", "development.php");
        $env_prod = CoreHelper::buildPath(CoreHelper::rootDir(), "config", "environments", "production.php");
        if (Config::instance()->isProduction() && is_file($env_prod))
            include_once $env_prod;
        elseif (Config::instance()->isDevelopment() && is_file($env_devel))
            include_once $env_devel;

        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();

        // --- include all app models ---
        $app_models_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "models");
        $app_models = CoreHelper::scanDir($app_models_dir);
        foreach ($app_models as $model) {
            $fExt = pathinfo($model, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            include_once $model;
        }

        // --- load initializers ---
        $initializers_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "config", "initializers");
        $app_initializers = CoreHelper::scanDir($initializers_dir);
        foreach ($app_initializers as $initializer) {
            if (!preg_match("/^.*\.php$/", $initializer))
                continue;
            include_once $initializer;
        }

        // --- load & include app channels ---
        $appChannels = $this->loadApplicationChannels();

        $infoMsg = "[". self::class ."] Loaded application channels: " . count($appChannels);
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";
        foreach ($appChannels as $key => $channel) {
            $infoMsg = "[". self::class ."]   - $key ($channel)";
            Logger::info($infoMsg);
            echo "$infoMsg\r\n";
        }

        // --- start workers ---
        for ($i = 0; $i < $this->_workersNum; $i++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork(); // create a fork
            if ($pid == -1) {
                $errMsg = "[" . self::class . "] Create fork failed (error pcntl_fork)!";
                Logger::error($errMsg);
                fwrite(STDERR, "$errMsg\r\n");
                die();
            } else if ($pid) { // parent process
                fclose($pair[0]);
                $this->_workersControls[] = $pair[1];
            } else if ($pid == 0) { // child process
                fclose($pair[1]);
                $worker = new WSWorker($this->_mountPath, $this->_isEnabledPerform, $appChannels, $server, $pair[0]);
                $worker->start();
                break;
            }
        }

        // --- start server adapter ---
        $adapter = null;
        $adapterName = WSConfig::instance()->currentAdapterName();
        if (strcmp(trim(strtolower($adapterName)), 'ipc') === 0)
            $adapter = new IPCServerAdapter($this->_workersControls);
        else if (strcmp(trim(strtolower($adapterName)), 'redis') === 0)
            $adapter = new RedisServerAdapter($this->_workersControls);

        if (is_null($adapter)) {
            $errMsg = "[" . self::class . "] Not found adapter with name \"$adapterName\"!";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $adapter->run();
    }

    /**
     * ?????????????????? ???????????? ?????????????? ????????????????????
     * @return array
     */
    protected function loadApplicationChannels(): array
    {
        // --- include base channels class ---
        $base_channel = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "channels", "Channel.php");
        if (is_file($base_channel))
            include_once $base_channel;

        // --- search, load & include all app channels ---
        $channels = [];
        $app_channels_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "channels");
        $app_channels = CoreHelper::scanDir($app_channels_dir);
        foreach ($app_channels as $channel) {
            $tmpName = $this->channelClass($channel);
            if (empty($tmpName) || strcmp($tmpName, 'Channel') === 0)
                continue;
            try {
                $classes = get_declared_classes();
                include_once $channel;
                $diff = array_diff(get_declared_classes(), $classes);
                reset($diff);
                foreach ($diff as $cName) {
                    try {
                        $tmpClass = new $cName();
                        if (is_subclass_of($tmpClass, '\FlyCubePHP\WebSockets\ActionCable\BaseChannel')) {
                            $tmpRef = new \ReflectionClass($tmpClass);
                            $tmpName = $tmpRef->getShortName();
                            $channels[$tmpName] = $cName;
                        }
                        unset($tmpClass);
                    } catch (\Exception $e) {
                        // nothing...
                    }
                }
            } catch (\Exception $e) {
                // nothing...
            }
        }
        return $channels;
    }

    /**
     * ???????????????? ?????? ???????????? ???????????? ???? ?????? ???????? ?? ??????????
     * @param string $path
     * @return string
     */
    protected function channelClass(string $path): string
    {
        if (preg_match("/^.*Channel\.php$/", $path) && preg_match('/(.*)\.php$/', basename($path), $matches))
            return $matches[1];
        return "";
    }
}