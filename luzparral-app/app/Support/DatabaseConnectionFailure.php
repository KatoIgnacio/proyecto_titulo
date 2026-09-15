<?php

namespace App\Support;

use PDOException;
use Throwable;

final class DatabaseConnectionFailure
{
    /** @var list<int> */
    private const MYSQL_CONNECTION_CODES = [1042, 1045, 1049, 1129, 1130, 2002, 2003, 2005, 2006, 2013];

    public static function matches(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $sqlState = (string) $current->getCode();

            if (str_starts_with($sqlState, '08')) {
                return true;
            }

            if (! $current instanceof PDOException) {
                continue;
            }

            $driverCode = is_array($current->errorInfo ?? null)
                ? (int) ($current->errorInfo[1] ?? 0)
                : (int) $current->getCode();

            if (in_array($driverCode, self::MYSQL_CONNECTION_CODES, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{exception: string, sql_state: string, driver_code: int} */
    public static function diagnosticContext(Throwable $exception): array
    {
        $databaseException = $exception;

        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof PDOException) {
                $databaseException = $current;
                break;
            }
        }

        $errorInfo = $databaseException instanceof PDOException && is_array($databaseException->errorInfo ?? null)
            ? $databaseException->errorInfo
            : [];

        return [
            'exception' => $exception::class,
            'sql_state' => (string) ($errorInfo[0] ?? $databaseException->getCode()),
            'driver_code' => (int) ($errorInfo[1] ?? $databaseException->getCode()),
        ];
    }
}
