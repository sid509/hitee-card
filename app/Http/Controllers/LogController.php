<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    /**
     * Display log entries from a selected log file
     */
    public function index(Request $request)
    {
        $logDir = storage_path('logs');
        
        // 1. Get all .log files and sort by modification time (newest first)
        $files = File::glob($logDir . '/*.log');
        $logFiles = collect($files)->map(function ($path) {
            return [
                'name' => basename($path),
                'path' => $path,
                'modified' => File::lastModified($path),
                'size' => File::size($path)
            ];
        })->sortByDesc('modified')->values();

        // 2. Determine which file to read
        $selectedFileName = $request->get('file', $logFiles->first()['name'] ?? 'laravel.log');
        $logPath = $logDir . '/' . $selectedFileName;

        $lines = (int) $request->get('lines', 100);
        $lines = max(1, min($lines, 2000));

        if (!File::exists($logPath)) {
            $logContent = "Log file not found at: " . $selectedFileName;
        } else {
            // Using tail command for performance if available, fallback to PHP
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $logContent = shell_exec("tail -n $lines " . escapeshellarg($logPath));
            } else {
                $logContent = $this->readLastLinesPhp($logPath, $lines);
            }
        }

        return view('modules.logs.index', compact('logContent', 'lines', 'logFiles', 'selectedFileName'));
    }

    /**
     * Clear a specific log file
     */
    public function clear(Request $request)
    {
        $fileName = $request->get('file', 'laravel.log');
        $logPath = storage_path('logs/' . $fileName);

        if (File::exists($logPath)) {
            File::put($logPath, '');
            return back()->with('success', "The {$fileName} log has been cleared successfully.");
        }
        return back()->with('error', 'Log file not found.');
    }

    /**
     * Fallback method to read last lines using PHP
     */
    private function readLastLinesPhp($filename, $lines)
    {
        $file = fopen($filename, "r");
        fseek($file, 0, SEEK_END);
        $pos = ftell($file);
        $content = "";
        $lineCount = 0;

        while ($pos > 0 && $lineCount <= $lines) {
            fseek($file, --$pos);
            $char = fgetc($file);
            if ($char == "\n") {
                $lineCount++;
            }
            if ($lineCount > $lines) break;
            $content = $char . $content;
        }
        fclose($file);
        return $content;
    }
}
