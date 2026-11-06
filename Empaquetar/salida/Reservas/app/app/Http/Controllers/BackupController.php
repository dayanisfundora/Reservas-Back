<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Generates and downloads a MySQL database dump (.sql) compatible with Navicat.
 */
class BackupController extends Controller
{
    /**
     * Runtime / cache tables: keep structure, skip data.
     *
     * @var list<string>
     */
    private const SKIP_DATA_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    /**
     * Never dump or restore these tables: dropping them mid-request kills the session.
     *
     * @var list<string>
     */
    private const PRESERVE_RUNTIME_TABLES = [
        'sessions',
        'personal_access_tokens',
    ];

    /**
     * Tables ignored completely by mysqldump (no data, no structure).
     *
     * @return list<string>
     */
    private function ignoredTables(): array
    {
        return array_values(array_unique([
            ...self::SKIP_DATA_TABLES,
            ...self::PRESERVE_RUNTIME_TABLES,
        ]));
    }

    /**
     * Run mysqldump and stream the resulting .sql file for download.
     *
     * @return BinaryFileResponse|JsonResponse
     */
    public function download(): BinaryFileResponse|JsonResponse
    {
        if (config('database.default') !== 'mysql') {
            return response()->json([
                'message' => 'El respaldo solo esta disponible cuando la conexion de base de datos es MySQL.',
            ], 422);
        }

        $connection = config('database.connections.mysql');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            return response()->json([
                'message' => 'Credenciales de base de datos incompletas en la configuracion.',
            ], 500);
        }

        $mysqldump = $this->resolveMysqldumpPath();
        $timestamp = now()->format('Y-m-d_His');
        $safeDatabase = preg_replace('/[^A-Za-z0-9_-]/', '_', $database) ?: 'database';
        $filename = "backup_{$safeDatabase}_{$timestamp}.sql";
        $path = storage_path('app/temp/' . $filename);

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) {
            return response()->json([
                'message' => 'No se pudo crear el directorio temporal para el respaldo.',
            ], 500);
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return response()->json([
                'message' => 'No se pudo abrir el archivo temporal del respaldo.',
            ], 500);
        }

        $quotedDatabase = str_replace('`', '``', $database);
        $header = implode("\n", [
            '-- Reservas backup for Navicat Execute SQL File',
            '-- Database: ' . $database,
            '-- Note: cache/jobs structure only; sessions and tokens are never dumped',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            'SET UNIQUE_CHECKS=0;',
            'USE `' . $quotedDatabase . '`;',
            '',
        ]);
        fwrite($handle, $header);

        $baseArgs = [
            $mysqldump,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--password=' . $password,
            '--single-transaction',
            '--quick',
            '--skip-triggers',
            '--hex-blob',
            '--add-drop-table',
            '--create-options',
            '--disable-keys',
            '--skip-extended-insert',
            '--complete-insert',
            '--skip-add-locks',
            '--skip-comments',
            '--set-gtid-purged=OFF',
            '--default-character-set=utf8mb4',
        ];

        $dataCommand = $baseArgs;
        foreach ($this->ignoredTables() as $table) {
            $dataCommand[] = '--ignore-table=' . $database . '.' . $table;
        }
        $dataCommand[] = $database;

        try {
            $dataResult = Process::timeout(300)->env($this->processEnv())->run($dataCommand, function (string $type, string $output) use ($handle): void {
                if ($type === 'out') {
                    fwrite($handle, $output);
                }
            });
        } catch (Throwable $e) {
            fclose($handle);
            $this->cleanupTempFile($path);

            return response()->json([
                'message' => 'No se pudo ejecutar mysqldump. Verifique que este instalado y que MYSQLDUMP_PATH sea correcto.',
                'error' => $e->getMessage(),
            ], 500);
        }

        if ($dataResult->failed()) {
            fclose($handle);
            $this->cleanupTempFile($path);
            $stderr = trim($dataResult->errorOutput());

            return response()->json([
                'message' => 'Error al generar el respaldo de la base de datos.',
                'error' => $stderr !== '' ? $stderr : 'mysqldump finalizo con codigo ' . $dataResult->exitCode(),
            ], 500);
        }

        $existingSkipTables = $this->existingTables($database, self::SKIP_DATA_TABLES);
        if ($existingSkipTables !== []) {
            $structureCommand = array_merge($baseArgs, [
                '--no-data',
                $database,
                ...$existingSkipTables,
            ]);

            try {
                $structureResult = Process::timeout(120)->env($this->processEnv())->run($structureCommand, function (string $type, string $output) use ($handle): void {
                    if ($type === 'out') {
                        fwrite($handle, $output);
                    }
                });
            } catch (Throwable $e) {
                fclose($handle);
                $this->cleanupTempFile($path);

                return response()->json([
                    'message' => 'No se pudo generar la estructura de tablas temporales.',
                    'error' => $e->getMessage(),
                ], 500);
            }

            if ($structureResult->failed()) {
                fclose($handle);
                $this->cleanupTempFile($path);
                $stderr = trim($structureResult->errorOutput());

                return response()->json([
                    'message' => 'Error al generar la estructura de tablas temporales.',
                    'error' => $stderr !== '' ? $stderr : 'mysqldump finalizo con codigo ' . $structureResult->exitCode(),
                ], 500);
            }
        }

        fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n");
        fclose($handle);

        if (!is_file($path) || filesize($path) < 100) {
            $this->cleanupTempFile($path);

            return response()->json([
                'message' => 'El respaldo se genero vacio o incompleto.',
            ], 500);
        }

        $contents = file_get_contents($path);
        if ($contents === false || !str_contains($contents, 'CREATE TABLE') || !str_contains($contents, 'SET FOREIGN_KEY_CHECKS=1;')) {
            $this->cleanupTempFile($path);

            return response()->json([
                'message' => 'El respaldo esta incompleto. Intente de nuevo.',
            ], 500);
        }

        // Normalize to UTF-8 without BOM / without NUL bytes (Navicat-safe).
        $contents = $this->toUtf8($contents);
        file_put_contents($path, $contents);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string) filesize($path),
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Restore the configured MySQL database from an uploaded .sql dump.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function restore(Request $request): JsonResponse
    {
        if (config('database.default') !== 'mysql') {
            return response()->json([
                'message' => 'La restauracion solo esta disponible cuando la conexion de base de datos es MySQL.',
            ], 422);
        }

        $request->validate([
            'file' => 'required|file|max:51200',
        ]);

        $file = $request->file('file');
        $originalName = strtolower((string) $file->getClientOriginalName());
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== 'sql' && !str_ends_with($originalName, '.sql')) {
            return response()->json([
                'message' => 'Solo se aceptan archivos .sql de respaldo.',
            ], 422);
        }

        $connection = config('database.connections.mysql');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            return response()->json([
                'message' => 'Credenciales de base de datos incompletas en la configuracion.',
            ], 500);
        }

        $sql = file_get_contents($file->getRealPath());
        if ($sql === false || trim($sql) === '') {
            return response()->json([
                'message' => 'El archivo de respaldo esta vacio.',
            ], 422);
        }

        $sql = $this->prepareRestoreSql($sql, $database);

        if (!str_contains($sql, 'CREATE TABLE') && !str_contains(strtoupper($sql), 'INSERT INTO')) {
            return response()->json([
                'message' => 'El archivo no parece un respaldo SQL valido.',
            ], 422);
        }

        $authUser = $request->user();
        $authUserId = $authUser?->id;

        // Close Laravel DB handles so DROP/REPLACE of other tables cannot stall this request.
        DB::purge();
        DB::disconnect();

        try {
            set_time_limit(300);
            $this->executeRestoreSql($host, (int) $port, $username, $password, $database, $sql);
        } catch (Throwable $e) {
            DB::reconnect();

            return response()->json([
                'message' => 'Error al restaurar la base de datos.',
                'error' => $e->getMessage(),
            ], 500);
        }

        DB::reconnect();

        $token = null;
        if ($authUserId) {
            try {
                $user = User::query()->find($authUserId);
                if ($user) {
                    // Keep the current browser session usable after restore.
                    $user->tokens()->delete();
                    $minutes = max(1, (int) config('sanctum.expiration', 15));
                    $token = $user->createToken('api-token', ['*'], now()->addMinutes($minutes))->plainTextToken;
                }
            } catch (Throwable) {
                $token = null;
            }
        }

        return response()->json([
            'message' => 'Base de datos restaurada correctamente.',
            'token' => $token,
            'keep_session' => $token !== null,
        ]);
    }

    /**
     * Normalize a dump so it restores into the configured database only.
     *
     * @param string $sql
     * @param string $database
     * @return string
     */
    private function prepareRestoreSql(string $sql, string $database): string
    {
        $sql = $this->toUtf8($sql);
        $sql = preg_replace('/^\s*(DROP\s+DATABASE|CREATE\s+DATABASE)\b.*?;/im', '', $sql) ?? $sql;
        $sql = preg_replace('/\/\*!40000\s+DROP DATABASE.*?\*\//is', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*USE\s+[`\w.-]+\s*;/im', '', $sql) ?? $sql;
        $sql = $this->stripPreservedRuntimeTables($sql);

        $quotedDatabase = str_replace('`', '``', $database);

        return implode("\n", [
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            'SET UNIQUE_CHECKS=0;',
            'USE `' . $quotedDatabase . '`;',
            $sql,
            'SET FOREIGN_KEY_CHECKS=1;',
            'SET UNIQUE_CHECKS=1;',
            '',
        ]);
    }

    /**
     * Remove DROP/CREATE/INSERT for session/token tables from an uploaded dump.
     *
     * @param string $sql
     * @return string
     */
    private function stripPreservedRuntimeTables(string $sql): string
    {
        foreach (self::PRESERVE_RUNTIME_TABLES as $table) {
            $quoted = preg_quote($table, '/');

            $sql = preg_replace(
                '/--\s*Table structure for table\s+[`"]?' . $quoted . '[`"]?\s*/i',
                '',
                $sql
            ) ?? $sql;

            $sql = preg_replace(
                '/--\s*Dumping data for table\s+[`"]?' . $quoted . '[`"]?\s*/i',
                '',
                $sql
            ) ?? $sql;

            $sql = preg_replace(
                '/DROP\s+TABLE\s+IF\s+EXISTS\s+[`"]?' . $quoted . '[`"]?\s*;/i',
                '',
                $sql
            ) ?? $sql;

            $sql = preg_replace(
                '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?' . $quoted . '[`"]?\s*\([\s\S]*?\)\s*ENGINE=[^;]*;/i',
                '',
                $sql
            ) ?? $sql;

            $sql = preg_replace(
                '/LOCK\s+TABLES\s+[`"]?' . $quoted . '[`"]?\s+WRITE\s*;/i',
                '',
                $sql
            ) ?? $sql;

            $sql = preg_replace(
                '/INSERT\s+INTO\s+[`"]?' . $quoted . '[`"]?\s[\s\S]*?;/i',
                '',
                $sql
            ) ?? $sql;

            $sql = preg_replace(
                '/\/\*!40000\s+ALTER\s+TABLE\s+[`"]?' . $quoted . '[`"]?[^;]*\*\/\s*;?/i',
                '',
                $sql
            ) ?? $sql;
        }

        return $sql;
    }

    /**
     * Run a prepared dump with PHP mysqli so restore does not depend on spawning mysql.exe.
     *
     * php artisan serve on Windows often fails to pipe stdin into the mysql client.
     *
     * @throws \RuntimeException
     */
    private function executeRestoreSql(
        string $host,
        int $port,
        string $username,
        string $password,
        string $database,
        string $sql
    ): void {
        if (! extension_loaded('mysqli')) {
            throw new \RuntimeException('La extension mysqli de PHP no esta habilitada.');
        }

        mysqli_report(MYSQLI_REPORT_OFF);

        $mysqli = @new \mysqli($host, $username, $password, $database, $port);
        if ($mysqli->connect_errno !== 0) {
            throw new \RuntimeException('No se pudo conectar a MySQL: ' . $mysqli->connect_error);
        }

        $mysqli->set_charset('utf8mb4');

        if (! $mysqli->multi_query($sql)) {
            $error = $mysqli->error;
            $mysqli->close();
            throw new \RuntimeException($error !== '' ? $error : 'MySQL rechazo el archivo SQL.');
        }

        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }

            if ($mysqli->errno !== 0) {
                $error = $mysqli->error;
                $mysqli->close();
                throw new \RuntimeException($error !== '' ? $error : 'Error al ejecutar el archivo SQL.');
            }

            if (! $mysqli->more_results()) {
                break;
            }
        } while ($mysqli->next_result());

        if ($mysqli->errno !== 0) {
            $error = $mysqli->error;
            $mysqli->close();
            throw new \RuntimeException($error !== '' ? $error : 'Error al ejecutar el archivo SQL.');
        }

        $mysqli->close();
    }

    /**
     * Resolve mysql client binary (same folder as mysqldump when possible).
     *
     * @return string
     */
    private function resolveMysqlClientPath(): string
    {
        $configured = trim((string) env('MYSQL_PATH', ''));
        if ($configured !== '') {
            return $configured;
        }

        $dumpPath = $this->resolveMysqldumpPath();
        $clientFromDump = preg_replace('/mysqldump(\.exe)?$/i', 'mysql$1', $dumpPath);
        if (is_string($clientFromDump) && $clientFromDump !== $dumpPath && is_file($clientFromDump)) {
            return $clientFromDump;
        }

        foreach (glob('C:\\laragon\\bin\\mysql\\mysql-*\\bin\\mysql.exe') ?: [] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $fallbackFiles = [
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysql.exe',
        ];

        foreach ($fallbackFiles as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return 'mysql';
    }

    /**
     * Resolve mysqldump binary (env override, PATH, or common Windows installs).
     *
     * @return string
     */
    private function resolveMysqldumpPath(): string
    {
        $configured = trim((string) env('MYSQLDUMP_PATH', ''));
        if ($configured !== '') {
            return $configured;
        }

        foreach (glob('C:\\laragon\\bin\\mysql\\mysql-*\\bin\\mysqldump.exe') ?: [] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $fallbackFiles = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
        ];

        foreach ($fallbackFiles as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    /**
     * Normalize dump text to UTF-8 without BOM / UTF-16 null bytes.
     *
     * @param string $sql
     * @return string
     */
    private function toUtf8(string $sql): string
    {
        if (str_starts_with($sql, "\xFF\xFE") || str_starts_with($sql, "\xFE\xFF")) {
            $converted = mb_convert_encoding($sql, 'UTF-8', 'UTF-16');
            if (is_string($converted) && $converted !== '') {
                $sql = $converted;
            }
        } elseif (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        } elseif (!mb_check_encoding($sql, 'UTF-8')) {
            $converted = mb_convert_encoding($sql, 'UTF-8', 'Windows-1252');
            if (is_string($converted) && $converted !== '') {
                $sql = $converted;
            }
        }

        return str_replace("\0", '', $sql);
    }

    /**
     * Filter skip-data table names to those that currently exist.
     *
     * @param string $database
     * @param list<string> $tables
     * @return list<string>
     */
    private function existingTables(string $database, array $tables): array
    {
        try {
            $rows = DB::select(
                'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (' . implode(',', array_fill(0, count($tables), '?')) . ')',
                [$database, ...$tables]
            );
        } catch (Throwable) {
            return $tables;
        }

        return array_values(array_map(
            static fn (object $row): string => (string) $row->name,
            $rows
        ));
    }

    /**
     * Environment for mysqldump: on Windows Winsock needs SystemRoot (error 2004/10106 otherwise),
     * and `php artisan serve` does not pass it to child processes.
     *
     * @return array<string, string>
     */
    private function processEnv(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $systemRoot = getenv('SystemRoot') ?: (getenv('windir') ?: 'C:\\Windows');

        return [
            'SystemRoot' => $systemRoot,
            'windir' => $systemRoot,
        ];
    }

    /**
     * Remove a temporary dump file if it exists.
     *
     * @param string $path
     * @return void
     */
    private function cleanupTempFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
