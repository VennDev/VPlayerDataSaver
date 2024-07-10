<?php

declare(strict_types=1);

namespace venndev\vplayerdatasaver;

use Throwable;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use venndev\vapmdatabase\database\mysql\MySQL;
use venndev\vapmdatabase\database\ResultQuery;
use venndev\vapmdatabase\database\sqlite\SQLite;
use venndev\vapmdatabase\database\handler\QueryHandler;
use vennv\vapm\FiberManager;
use vennv\vapm\Promise;
use vennv\vapm\VapmPMMP;

class VPlayerDataSaver extends PluginBase implements Listener
{

    private static QueryHandler $queryHandler;

    private static array $defaultDataYamlPlayer = [
        "xuid" => "",
        "name" => ""
    ];

    private static string $tableName;

    private static MySQL|SQLite|Config $database;

    /**
     * @throws Throwable
     */
    protected function onLoad(): void
    {
        $this->saveDefaultConfig();

        $type = $this->getDatabaseData()["type"];
        $host = $this->getDatabaseData()["host"];
        $port = $this->getDatabaseData()["port"];
        $username = $this->getDatabaseData()["username"];
        $password = $this->getDatabaseData()["password"];
        $database = $this->getDatabaseData()["database"];
        $nameTable = $this->getDatabaseData()["table-name"];
        $additionSQLQueries = $this->getDatabaseData()["addition-sql-queries"];
        $additionYAMLData= $this->getDatabaseData()["addition-yaml-data"];

        self::$tableName = $nameTable;
        if ($type === "mysql") {
            self::$database = new MySQL($host, $username, $password, $database, $port);
            self::$database->execute("CREATE TABLE IF NOT EXISTS $nameTable (xuid VARCHAR(16) PRIMARY KEY, name VARCHAR(16));");
        } elseif ($type === "sqlite") {
            $databaseFile = $database . ".db";
            if (!file_exists($this->getDataFolder() . $databaseFile)) $this->saveResource($databaseFile);
            self::$database = new SQLite($databaseFile);
            self::$database->execute("CREATE TABLE IF NOT EXISTS $nameTable (xuid TEXT PRIMARY KEY, name TEXT);");
        } elseif ($type === "yaml") {
            self::$database = new Config($this->getDataFolder() . "data.yml", Config::YAML);
        } else {
            $this->getLogger()->error("Invalid database type: $type");
        }

        if ($type === "mysql" || $type === "sqlite") {
            foreach ($additionSQLQueries as $query) {
                self::$database->execute($query);
            }
        } elseif ($type === "yaml") {
            self::$defaultDataYamlPlayer = array_merge(self::$defaultDataYamlPlayer, $additionYAMLData);
        }

        self::$queryHandler = new QueryHandler();
    }

    /**
     * @throws Throwable
     */
    protected function onEnable(): void
    {
        VapmPMMP::init($this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    protected function onDisable(): void
    {
        if (self::$database instanceof MySQL) self::$database->close();
    }

    protected function getDatabaseData(): array
    {
        return $this->getConfig()->get("database");
    }

    public static function getDatabase(): MySQL|SQLite|Config
    {
        return self::$database;
    }

    /**
     * @throws Throwable
     */
    public static function checkExists(string $xuid, callable $callable): Promise
    {
        if (self::$database instanceof MySQL || self::$database instanceof SQLite) {
            return new Promise(function ($resolve) use ($xuid, $callable):void {
                $queryString = "SELECT * FROM " . self::$tableName . " WHERE xuid = '$xuid';";
                $resolve(self::$queryHandler->processQuery($queryString, function() use ($xuid, $queryString, $callable): Promise {
                    return self::$database->execute($queryString)->then($callable);
                }));
            });
        } else {
            return new Promise(function ($resolve, $reject) use ($xuid):void {
                try {
                    $data = self::$database->getAll();
                    foreach ($data as $key => $value) {
                        if ($value["xuid"] === $xuid) {
                            $resolve($value["name"]);
                        }

                        FiberManager::wait();
                    }

                    $resolve(null);
                } catch (Throwable $e) {
                    $reject($e);
                }
            });
        }
    }

    /**
     * @throws Throwable
     */
    public static function addXUID(string $xuid, string $name, callable $callable): Promise
    {
        if (self::$database instanceof MySQL || self::$database instanceof SQLite) {
            return new Promise(function ($resolve) use ($xuid, $name, $callable):void {
                $queryString = "INSERT INTO " . self::$tableName . " (xuid, name) VALUES ('$xuid', '$name');";
                $resolve(self::$queryHandler->processQuery($queryString, function() use ($queryString, $callable): Promise {
                    return self::$database->execute($queryString)->then($callable);
                }));
            });
        } else {
            return new Promise(function ($resolve, $reject) use ($xuid, $name):void {
                try {
                    $data = self::$database->getAll();
                    $dataPlayer = ["xuid" => $xuid, "name" => $name];
                    $dataPlayer = array_merge(self::$defaultDataYamlPlayer, $dataPlayer);
                    $data[] = $dataPlayer;
                    self::$database->setAll($data);
                    self::$database->save();
                    $resolve(true);
                } catch (Throwable $e) {
                    $reject($e);
                }
            });
        }
    }

    /**
     * @throws Throwable
     */
    public static function updateName(string $xuid, string $name, callable $callable): Promise
    {
        if (self::$database instanceof MySQL || self::$database instanceof SQLite) {
            return new Promise(function ($resolve) use ($xuid, $name, $callable):void {
                $queryString = "UPDATE " . self::$tableName . " SET name = '$name' WHERE xuid = '$xuid';";
                $resolve(self::$queryHandler->processQuery($queryString, function() use ($queryString, $callable): Promise {
                    return self::$database->execute($queryString)->then($callable);
                }));
            });
        } else {
            return new Promise(function ($resolve, $reject) use ($xuid, $name):void {
                try {
                    $data = self::$database->getAll();
                    foreach ($data as $key => $value) {
                        if ($value["xuid"] === $xuid) {
                            $data[$key]["name"] = $name;
                            self::$database->setAll($data);
                            self::$database->save();
                            $resolve(true);
                        }

                        FiberManager::wait();
                    }

                    $resolve(false);
                } catch (Throwable $e) {
                    $reject($e);
                }
            });
        }
    }

    /**
     * @throws Throwable
     */
    public static function updateColumn(string $xuid, string $column, int|float|string $value, callable $callable): Promise
    {
        if (self::$database instanceof MySQL || self::$database instanceof SQLite) {
            if (is_string($value)) {
                $queryString = "UPDATE " . self::$tableName . " SET $column = '$value' WHERE xuid = '$xuid';";
            } else {
                $queryString = "UPDATE " . self::$tableName . " SET $column = $value WHERE xuid = '$xuid';";
            }

            return new Promise(function ($resolve) use ($xuid, $column, $value, $queryString, $callable):void {
                $resolve(self::$queryHandler->processQuery($queryString, function() use ($queryString, $callable): Promise {
                    return self::$database->execute($queryString)->then($callable);
                }));
            });
        } else {
            return new Promise(function ($resolve, $reject) use ($xuid, $column, $value):void {
                try {
                    $data = self::$database->getAll();
                    foreach ($data as $key => $dataPlayer) {
                        if ($dataPlayer["xuid"] === $xuid) {
                            $data[$key][$column] = $value;
                            self::$database->setAll($data);
                            self::$database->save();
                            $resolve(true);
                        }

                        FiberManager::wait();
                    }

                    $resolve(false);
                } catch (Throwable $e) {
                    $reject($e);
                }
            });
        }
    }

    /**
     * @throws Throwable
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $xuid = $player->getXuid();

        try {
            self::checkExists($xuid, function ($result) use ($player, $xuid):void {
                $callable = function () {};
                if ($result instanceof ResultQuery) {
                    empty($result->getResult()) ? self::addXUID($xuid, $player->getName(), $callable) : $this->updateName($xuid, $player->getName(), $callable);
                } else {
                    $result === null ? self::addXUID($xuid, $player->getName(), $callable) : $this->updateName($xuid, $player->getName(), $callable);
                }
            });
        } catch (Throwable $e) {
            $this->getLogger()->error($e->getMessage());
        }
    }

}