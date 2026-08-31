<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Respaldo (dump) de la base de datos — cronograma del documento, sección 2.3:
 * "exportación periódica (dump) de la base de datos como medida de contingencia".
 *
 *   php artisan backup:db            → storage/app/backups/goldstreet_YYYYmmdd_His.sql
 *   php artisan backup:db --keep=14  → conserva los 14 más recientes
 *
 * Restaurar:  mysql -u root goldstreet < storage/app/backups/archivo.sql
 */
class BackupDb extends Command
{
    protected $signature = 'backup:db {--keep=7 : Cantidad de respaldos a conservar (los más viejos se borran)}';

    protected $description = 'Genera un respaldo (dump) de la base de datos en storage/app/backups';

    public function handle(): int
    {
        $conexion = config('database.default');
        $cfg = config("database.connections.{$conexion}");
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $sello = now()->format('Ymd_His');

        $archivo = match ($cfg['driver']) {
            'mysql', 'mariadb' => $this->dumpMysql($cfg, "{$dir}/{$cfg['database']}_{$sello}.sql"),
            'sqlite'           => $this->copiarSqlite($cfg, "{$dir}/database_{$sello}.sqlite"),
            default            => $this->fallo("Motor '{$cfg['driver']}' no soportado para respaldo."),
        };

        if (! $archivo) {
            return self::FAILURE;
        }

        $this->info('Respaldo creado: '.$archivo.' ('.round(filesize($archivo) / 1024, 1).' KB)');
        $this->depurar($dir, max(1, (int) $this->option('keep')));

        return self::SUCCESS;
    }

    private function dumpMysql(array $cfg, string $archivo): ?string
    {
        $proceso = new Process([
            $this->binarioMysqldump(),
            '--host='.$cfg['host'],
            '--port='.$cfg['port'],
            '--user='.$cfg['username'],
            '--single-transaction',      // copia consistente sin bloquear las tablas
            '--routines',
            '--triggers',                // incluye trg_mov_stock
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$archivo,
            $cfg['database'],
        ], null, ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);   // la contraseña no queda en la línea de comandos

        $proceso->setTimeout(300);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            File::delete($archivo);

            return $this->fallo("mysqldump falló: ".trim($proceso->getErrorOutput() ?: $proceso->getOutput())
                ."\nSi no se encuentra mysqldump, define DB_DUMP_BINARY en el .env con su ruta completa.");
        }

        return $archivo;
    }

    private function copiarSqlite(array $cfg, string $archivo): ?string
    {
        if ($cfg['database'] === ':memory:' || ! File::exists($cfg['database'])) {
            return $this->fallo('No hay archivo SQLite que respaldar.');
        }
        File::copy($cfg['database'], $archivo);

        return $archivo;
    }

    /** DB_DUMP_BINARY del .env › mysqldump de XAMPP › mysqldump del PATH */
    private function binarioMysqldump(): string
    {
        if ($ruta = config('database.dump_binary')) {
            return $ruta;
        }
        $xampp = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

        return File::exists($xampp) ? $xampp : 'mysqldump';
    }

    private function depurar(string $dir, int $conservar): void
    {
        $viejos = collect(File::files($dir))
            ->filter(fn ($f) => in_array($f->getExtension(), ['sql', 'sqlite'], true))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->slice($conservar);

        $viejos->each(fn ($f) => File::delete($f->getPathname()));

        if ($viejos->isNotEmpty()) {
            $this->line("Se eliminaron {$viejos->count()} respaldo(s) antiguo(s); se conservan {$conservar}.");
        }
    }

    private function fallo(string $mensaje): null
    {
        $this->error($mensaje);

        return null;
    }
}
