<?php

namespace ChipiTiempo;

/**
 * ChipiTiempo - Sistema de cache simple para datos de AEMET
 * 
 * Evita solicitudes repetidas a la API de AEMET dentro de un período de tiempo
 */
class Cache {
    private string $cacheDir;
    private int $ttl = 300; // 5 minutos por defecto
    
    public function __construct(string $cacheDir = '', int $ttlSeconds = 300) {
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir();
        $this->ttl = $ttlSeconds;
        
        // Asegurar que el directorio existe
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Obtener datos del cache
     * 
     * @param string $key Clave del cache
     * @return mixed|null Datos si existen y son válidos, null si no
     */
    public function get(string $key): mixed {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }
        
        try {
            return unserialize($data);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Guardar datos en cache
     * 
     * @param string $key Clave del cache
     * @param mixed $data Datos a guardar
     * @return bool Éxito de la operación
     */
    public function set(string $key, mixed $data): bool {
        $file = $this->getCacheFile($key);
        
        try {
            return @file_put_contents($file, serialize($data)) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Verificar si el cache es válido (no expirado)
     * 
     * @param string $key Clave del cache
     * @return bool true si el cache existe y no ha expirado
     */
    public function isValid(string $key): bool {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return false;
        }
        
        return (time() - $mtime) < $this->ttl;
    }
    
    /**
     * Obtener datos si el cache es válido, null si no
     * 
     * @param string $key Clave del cache
     * @return mixed|null
     */
    public function getIfValid(string $key): mixed {
        if (!$this->isValid($key)) {
            return null;
        }
        return $this->get($key);
    }
    
    /**
     * Obtener datos del cache incluso si está expirado (fallback para cuando la API falla)
     * 
     * @param string $key Clave del cache
     * @return mixed|null Datos si existen (aunque estén expirados), null si no existen
     */
    public function getStale(string $key): mixed {
        return $this->get($key);
    }
    
    /**
     * Limpiar cache expirado
     */
    public function cleanup(): void {
        if (!is_dir($this->cacheDir)) {
            return;
        }
        
        $files = @scandir($this->cacheDir);
        if (!$files) {
            return;
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $this->cacheDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            
            $mtime = @filemtime($path);
            if ($mtime === false || (time() - $mtime) > ($this->ttl * 2)) {
                @unlink($path);
            }
        }
    }
    
    /**
     * Limpiar todo el cache
     */
    public function clear(): void {
        if (!is_dir($this->cacheDir)) {
            return;
        }
        
        $files = @scandir($this->cacheDir);
        if (!$files) {
            return;
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $this->cacheDir . '/' . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    
    private function getCacheFile(string $key): string {
        return $this->cacheDir . '/chipitiempo_' . md5($key) . '.cache';
    }
}
