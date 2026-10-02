<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guard regresi: mencegah plugin "activity logger" dipasang kembali
 * tanpa disadari.
 *
 * Latar belakang insiden: `jacobtims/filament-logger` memasang observer pada
 * model dari SETIAP Filament Resource yang terdaftar. Karena resource
 * ActivityResource memakai model Activity, setiap baris `activity_log` baru
 * memicu penulisan baris berikutnya tanpa henti, dan kolom `properties`
 * menyalin dirinya sendiri tiap level sehingga payload membengkak
 * eksponensial. Akibatnya MySQL melempar 1153 "packet bigger than
 * max_allowed_packet" (08S01), yang tampil di aplikasi Android sebagai
 * 2006 "MySQL server has gone away" saat scan.
 *
 * Plugin sudah dihapus total. Test ini memastikan tidak ada yang
 * memasangnya kembali.
 */
class ActivityLogRecursionGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tabel activity_log tidak boleh dibuat lagi oleh migration.
     */
    public function test_activity_log_table_is_not_migrated(): void
    {
        $this->assertFalse(
            Schema::hasTable('activity_log'),
            'Tabel activity_log dibuat kembali oleh migration. Fitur ini sudah '
            .'dilepas total karena memicu rekursi tak terbatas (MySQL 1153/2006).'
        );
    }

    /**
     * Tidak ada model yang boleh diamati oleh logger berbasis activity log.
     */
    public function test_no_activity_log_plugin_is_installed(): void
    {
        foreach ([
            'Jacobtims\FilamentLogger\FilamentLoggerPlugin',
            'Jacobtims\FilamentLogger\Loggers\ResourceLogger',
            'Spatie\Activitylog\Models\Activity',
        ] as $class) {
            $this->assertFalse(
                class_exists($class),
                "Class {$class} terpasang kembali. Plugin activity logger "
                .'menyebabkan rekursi tak terbatas dan error MySQL 1153/2006.'
            );
        }

        $this->assertFileDoesNotExist(
            config_path('filament-logger.php'),
            'Config filament-logger masih ada padahal pluginnya sudah dilepas.'
        );
    }
}
