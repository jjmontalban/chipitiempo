<?php

namespace ChipiTiempo\Logging;

/**
 * Sistema de logging centralizado
 * 
 * Soporta:
 * - Escritura en STDOUT (consola)
 * - Escritura en archivos
 * - Niveles de severidad (DEBUG, INFO, WARNING, ERROR)
 * - Contexto adicional
 */
class Logger {
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';

    private static string $logFile = '';
    private static bool $consoleOutput = true;

    /**
     * Inicializar logger con archivo de salida
     */
    public static function init(string $logFile = '', bool $consoleOutput = true): void {
        self::$logFile = $logFile;
        self::$consoleOutput = $consoleOutput;
        
        if ($logFile && !is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
    }

    /**
     * Log DEBUG
     */
    public static function debug(string $message, array $context = []): void {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log INFO
     */
    public static function info(string $message, array $context = []): void {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log WARNING
     */
    public static function warning(string $message, array $context = []): void {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log ERROR
     */
    public static function error(string $message, array $context = []): void {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log con nivel personalizado
     */
    public static function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = self::format($timestamp, $level, $message, $context);

        // Enviar a STDOUT
        if (self::$consoleOutput) {
            echo $formatted . PHP_EOL;
        }

        // Enviar a archivo
        if (self::$logFile) {
            file_put_contents(self::$logFile, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Formatear línea de log
     */
    private static function format(string $timestamp, string $level, string $message, array $context): string {
        $parts = ["[{$timestamp}]", "[{$level}]", $message];
        
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $parts[] = $contextStr;
        }
        
        return implode(' ', $parts);
    }
}
