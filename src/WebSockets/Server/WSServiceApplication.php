<?php

namespace FlyCubePHP\WebSockets\Server;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\HelperClasses\CoreHelper;

include_once __DIR__.'/../../FlyCubePHPVersion.php';
include_once __DIR__.'/../../FlyCubePHPAutoLoader.php';
include_once __DIR__.'/../../FlyCubePHPErrorHandling.php';
include_once __DIR__.'/../../FlyCubePHPEnvLoader.php';
include_once __DIR__.'/../../Core/Logger/Logger.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

include_once 'WSServer.php';

class WSServiceApplication
{
    const PID_FILE_NAME = "ws.pid";

    public function start()
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $appPath = $dbt[0]['file'];
        if ($this->startService($appPath) === true)
            die("WSServiceApplication started\r\n");

        die("WSServiceApplication start failed!\r\n");
    }

    public function stop()
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $appPath = $dbt[0]['file'];
        if ($this->stopService($appPath) === true)
            die("WSServiceApplication stopped\r\n");

        die("WSServiceApplication stop failed!\r\n");
    }

    public function restart()
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $appPath = $dbt[0]['file'];
        $pidFile = CoreHelper::buildPath(CoreHelper::rootDir(), 'tmp', self::PID_FILE_NAME);
        $pid = trim(@file_get_contents($pidFile));
        if ($pid) {
            echo "Check...$appPath\r\n";
            $ret = $this->isCopyOfCurrentProcess($pid, $appPath);
            var_dump($ret);
            if ($ret) {
                echo "Stop...$appPath\r\n";
                if ($this->stopService($appPath) === false)
                    die("WSServiceApplication stop failed!\r\n");
            }

        }
        echo "Start...\r\n";
        if ($this->startService($appPath) === false)
            die("WSServiceApplication start failed!\r\n");
    }

    private function startService(string $appPath): bool
    {
        $pidFile = CoreHelper::buildPath(CoreHelper::rootDir(), 'tmp', self::PID_FILE_NAME);
        $pid = trim(@file_get_contents($pidFile));
        if ($pid) {
            if ($this->isCopyOfCurrentProcess($pid, $appPath))
                return false;
            else
                unlink($pidFile);
        }

        $pid = pcntl_fork(); // create a fork
        if ($pid == -1) {
            Logger::error("[" . self::class . "] Create fork failed (error pcntl_fork)!");
            return false;
        } else if ($pid == 0) { // child started
            $sid = posix_setsid();
            $pid = posix_getpid();
//            echo "PID: $pid\r\n";
//            echo "SID: $sid\r\n";
            if ($sid < 0)
                exit;
            // --- save PID ---
            file_put_contents($pidFile, posix_getpid());
            // --- start ws server loop ---
            $server = new WSServer();
            $server->start();
        }
        return true;
    }

    private function stopService(string $appPath): bool
    {
        $pidFile = CoreHelper::buildPath(CoreHelper::rootDir(), 'tmp', self::PID_FILE_NAME);
        $pid = trim(@file_get_contents($pidFile));
        if ($pid) {
            if ($this->isCopyOfCurrentProcess($pid, $appPath) === false)
                return true;

            posix_kill($pid, SIGTERM);
            for ($i = 0; $i = 10; $i++) {
                sleep(1);
                if (!posix_getpgid($pid)) {
                    unlink($pidFile);
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    private function isCopyOfCurrentProcess($pid, $appPath)
    {
        if (!file_exists("/proc/$pid"))
            return false;
//        echo "$pid\r\n";
//        echo "$appPath\r\n";

        // get process all name
        $bPath = "/proc/$pid/cmdline";
        if ($file = fopen($bPath, "r")) {
            $line = fgets($file);
            fclose($file);
            if (!empty($line)) {
                $line = $this->prepareCmdLineData(rtrim($line));
                echo "$line\r\n";
                if (strpos($line, "php") === 0)
                    $line = substr($line, 3, strlen($line));

//                echo "$line\r\n";

                if (!empty($line) && preg_match('/(.*)(\sstart|\sstop|\srestart)/', $line, $matches))
                    $line = $matches[1];

//                var_dump($matches);
//                echo "$line\r\n";

                $buffLst = explode("/", $line);
                $pName = rtrim($buffLst[count($buffLst) - 1]);
                unset($buffLst[count($buffLst) - 1]);
                foreach ($buffLst as $item) {
                    if (empty($pPath))
                        $pPath = rtrim($item);
                    else
                        $pPath .= "/" . rtrim($item);
                }

                // check is link
                if (is_link($pPath) === true)
                    $pPath = readlink($pPath);
            }

        } else {
            echo "Open file failed (ReadOnly)! Path: $bPath\r\n";
            return false;
        }

//        echo "$pName\r\n";
//        echo "$pPath\r\n";

        // check
        if (strcmp("$pPath/$pName", $appPath) === 0)
            return true;

        // get process path
        $bPath = "/proc/$pid/cwd";
        if (is_link($bPath) === true) {
            $pPath = readlink($bPath) . "/$pName";
        } else {
            echo "Open symlink failed (ReadOnly)! Path: $bPath\r\n";
            return false;
        }

//        echo "$pPath\r\n";

        // check
        return (strcmp($pPath, $appPath) === 0);
    }

    private function prepareCmdLineData(string $line): string {
        $tmpLine = "";
        $size = strlen($line);
        for ($i = $size - 1; $i >= 0; $i--) {
            $ch = $line[$i];
            if (ord($ch) === 0)
                $ch = ' ';
//                break;
            $tmpLine = $ch . $tmpLine;
        }
        return $tmpLine;
    }
}