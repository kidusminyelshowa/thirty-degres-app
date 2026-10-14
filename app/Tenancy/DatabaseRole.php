<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Str;
use PDO;

/**
 * Runs every app query as the restricted Postgres role (DB_APP_ROLE,
 * default thirty_app), which has no way around row-level security.
 *
 * The login user from DB_USERNAME owns the tables and may be a superuser or
 * have BYPASSRLS (managed Postgres services often do), so it is only used
 * as itself for schema commands: migrations, wiping and seeding.
 */
class DatabaseRole
{
    /** @var list<string> */
    private const SCHEMA_COMMANDS = ['migrate', 'migrate:*', 'db:wipe', 'db:seed', 'schema:*'];

    private bool $runningSchemaCommand = false;

    public function __construct(private DatabaseManager $db) {}

    public function register(Dispatcher $events): void
    {
        $events->listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $this->assumeOnConnect($event->connection);
        });

        $events->listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($this->isSchemaCommand($event->command)) {
                $this->suspend();
            }
        });

        $events->listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($this->isSchemaCommand($event->command)) {
                $this->resume();
            }
        });
    }

    /**
     * Use the login user itself, for changing the schema.
     */
    public function suspend(): void
    {
        $this->runningSchemaCommand = true;
        $this->forEachOpenConnection(fn (Connection $connection) => $this->drop($connection));
    }

    public function resume(): void
    {
        $this->runningSchemaCommand = false;
        $this->forEachOpenConnection(fn (Connection $connection) => $this->assume($connection));
    }

    /**
     * The role queries on this connection should run as, or null for none.
     */
    public static function for(Connection $connection): ?string
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return null;
        }

        $role = $connection->getConfig('app_role');

        return is_string($role) && $role !== '' ? $role : null;
    }

    /**
     * Laravel opens the actual database connection lazily, on the first query.
     * Switch role at that moment, so nothing ever runs as the login user.
     */
    private function assumeOnConnect(Connection $connection): void
    {
        $pdo = $connection->getRawPdo();

        if ($pdo instanceof PDO) {
            if (! $this->runningSchemaCommand) {
                $this->assume($connection);
            }

            return;
        }

        if ($pdo instanceof Closure) {
            $connection->setPdo(function () use ($pdo, $connection): PDO {
                $resolved = $pdo();
                $role = self::for($connection);

                if ($role !== null && ! $this->runningSchemaCommand) {
                    $resolved->exec('set role '.$connection->getQueryGrammar()->wrap($role));
                }

                return $resolved;
            });
        }
    }

    private function assume(Connection $connection): void
    {
        $role = self::for($connection);

        if ($role !== null) {
            $connection->statement('set role '.$connection->getQueryGrammar()->wrap($role));
        }
    }

    private function drop(Connection $connection): void
    {
        if (self::for($connection) !== null) {
            $connection->statement('reset role');
        }
    }

    /**
     * @param  callable(Connection): void  $callback
     */
    private function forEachOpenConnection(callable $callback): void
    {
        foreach ($this->db->getConnections() as $connection) {
            if ($connection->getRawPdo() instanceof PDO) {
                $callback($connection);
            }
        }
    }

    private function isSchemaCommand(?string $command): bool
    {
        return $command !== null && Str::is(self::SCHEMA_COMMANDS, $command);
    }
}
