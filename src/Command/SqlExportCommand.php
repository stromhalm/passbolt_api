<?php
declare(strict_types=1);

/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SA (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SA (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         2.0.0
 */
namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\Connection;
use Cake\Database\Driver;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;

class SqlExportCommand extends PassboltCommand
{
    use DatabaseAwareCommandTrait;

    /**
     * Where the dumps get exported by default.
     */
    public const CACHE_DATABASE_DIRECTORY = CACHE . 'database' . DS;

    /**
     * @inheritDoc
     */
    public static function getCommandDescription(): string
    {
        return __('Utility to export SQL database backups.') .
            ' ' . __('Replaces the deprecated mysql_export command.');
    }

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        $this
            ->addDatasourceOption($parser, false)
            ->addOption('clear-previous', [
                'boolean' => true,
                'default' => false,
                'help' => __('Clear previous backups.'),
            ])
            ->addOption('force', [
                'boolean' => true,
                'default' => false,
                'help' => __('Override if file already exist.'),
            ])
            ->addOption('file', [
                'help' => 'The file to export the schema and data to.',
                'default' => null,
            ])
            ->addOption('dir', [
                'help' => 'The directory to export the schema and data to.',
                'default' => null,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        parent::execute($args, $io);

        // Check if the data source is in the config
        /** @var string $datasource */
        $datasource = $args->getOption('datasource');
        try {
            /** @var \Cake\Database\Connection $connection */
            $connection = ConnectionManager::get($datasource);
        } catch (MissingDatasourceConfigException $e) {
            $this->error($e->getMessage(), $io);

            return $this->errorCode();
        }

        // Define location and dump the table and data
        $dir = $this->getDir($args, $io);
        if (empty($dir)) {
            return $this->errorCode();
        }
        $file = $this->getFile($dir, $args, $io);

        if (empty($file)) {
            return $this->errorCode();
        }
        $status = $this->dump($connection, $dir, $file, $io);
        if (!$status) {
            $this->error('Something went wrong!', $io);

            return $this->errorCode();
        }

        if ($args->getOption('clear-previous')) {
            $this->clearPrevious($dir, $file, $io);
        }

        $this->success(__('Success: the database was saved on file!'), $io);

        return $this->successCode();
    }

    /**
     * Perform a mysqldump command
     *
     * @param \Cake\Database\Connection $connection connection manager config
     * @param string $dir directory path
     * @param string $file file name
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return bool
     */
    protected function dump(Connection $connection, string $dir, string $file, ConsoleIo $io): bool
    {
        $io->info('Saving backup file: ' . $dir . $file);

        $driver = $connection->getDriver();
        if ($driver instanceof Mysql) {
            // mariadb-dump command was introduced in v10.4.6, before that it doesn't exist
            // @see https://mariadb.com/kb/en/mariadb-dump/
            $isMariadbDump = ($driver->isMariaDb() && version_compare($driver->version(), '10.4.6', '>='));

            if (!$isMariadbDump) {
                $status = $this->mysqlDump($connection->config(), $dir, $file);
            } else {
                $status = $this->mariaDbDump($connection->config(), $dir, $file);
            }
        } elseif ($driver instanceof Postgres) {
            $status = $this->postgresDump($connection->config(), $dir, $file);
        } else {
            $this->error('Sorry only MySQL and PostgreSQL are supported at the moment', $io);

            return false;
        }

        if ($status !== $this->successCode()) {
            $io->quiet(__('There was an error running the dump.'));
            $io->error(__('Please ensure on MySQL that the user has PROCESS privileges.'));
            $io->error(__('Database ') . $connection->config()['database']);
            $io->error(__('User ') . $connection->config()['username']);

            return false;
        }

        return true;
    }

    /**
     * MySQL dump
     *
     * @param array $config Databse config
     * @param string $dir Target directory
     * @param string $file Target file
     * @return int
     */
    protected function mysqlDump(array $config, string $dir, string $file): int
    {
        // Build the dump command.
        $cmd = 'mysqldump -h' . escapeshellarg($config['host']) . ' -u' . escapeshellarg($config['username']);
        if (!empty($config['password'])) {
            $cmd .= ' -p' . escapeshellarg($config['password']);
        }
        $cmd .= ' ' . escapeshellarg($config['database']) . ' > ' . $dir . $file;
        exec($cmd, $output, $status);

        return $status;
    }

    /**
     * Mariadb dump.
     *
     * @param array $config Database configuration.
     * @param string $dir Target directory.
     * @param string $file Target file.
     * @return int
     */
    protected function mariaDbDump(array $config, string $dir, string $file): int
    {
        // Build the dump command.
        $cmd = 'mariadb-dump -h' . escapeshellarg($config['host']) . ' -u' . escapeshellarg($config['username']);
        if (!empty($config['password'])) {
            $cmd .= ' -p' . escapeshellarg($config['password']);
        }
        $cmd .= ' ' . escapeshellarg($config['database']) . ' > ' . $dir . $file;
        exec($cmd, $output, $status);

        return $status;
    }

    /**
     * PostgreSQL dump
     *
     * @param array $config Databse config
     * @param string $dir Target directory
     * @param string $file Target file
     * @return int
     */
    protected function postgresDump(array $config, string $dir, string $file): int
    {
        // Build the dump command.
        $cmd = 'PGPASSWORD=' . escapeshellarg($config['password']) . ' pg_dump -h ' . escapeshellarg($config['host']);
        $cmd .= ' -U ' . escapeshellarg($config['username']);
        $cmd .= ' -d ' . escapeshellarg($config['database']) . ' > ' . $dir . $file;
        exec($cmd, $output, $status);

        return $status;
    }

    /**
     * Check if the driver is supported by Passbolt.
     *
     * @param \Cake\Database\Driver $driver Driver to assess.
     * @return bool
     */
    protected function isSupportedDriver(Driver $driver): bool
    {
        return $driver instanceof Mysql || $driver instanceof Postgres;
    }

    /**
     * Clear any previously saved backup file
     *
     * @param string $dir directory
     * @param string $newFile name, to avoid deleting it
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return void
     */
    protected function clearPrevious(string $dir, string $newFile, ConsoleIo $io): void
    {
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && $file !== $dir . $newFile) {
                if (strpos($file, 'empty') !== false) {
                    continue;
                }
                unlink($file);
                $io->out('Deleting previous backup: ' . $file);
            }
        }
    }

    /**
     * Get the file where to import the backup from
     *
     * @param string $dir directory
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return string|null
     */
    protected function getFile(string $dir, Arguments $args, ConsoleIo $io): ?string
    {
        $file = $args->getOption('file');
        if (!is_string($file) || empty($file)) {
            $file = 'backup_' . time() . '.sql';
        }
        if (file_exists($dir . $file)) {
            $force = $args->getOption('force');
            if (!$force) {
                $this->error(__('The backup file already exist: ' . $dir . $file), $io);

                return null;
            }
        }

        return $file;
    }

    /**
     * Get the directory where to import the backup from
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console IO.
     * @return string|null or null if not exist
     */
    protected function getDir(Arguments $args, ConsoleIo $io): ?string
    {
        $dir = $args->getOption('dir');
        if (empty($dir)) {
            $dir = self::CACHE_DATABASE_DIRECTORY;
            if (!file_exists($dir)) {
                mkdir($dir);
            }
        }
        if (!is_string($dir) || !file_exists($dir)) {
            $this->error(__('Could not access the backup directory: ' . $dir), $io);

            return null;
        }
        if (substr($dir, -1) !== DS) {
            $dir .= DS;
        }

        return $dir;
    }
}
