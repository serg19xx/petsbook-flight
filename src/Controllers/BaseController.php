<?php

namespace App\Controllers;

class BaseController {
    protected function response($success, $data = null, $error_code = null) {
        $response = ['success' => $success];
        
        if ($success && $data) {
            $response = array_merge($response, $data);
        }
        
        if (!$success && $error_code) {
            $response['error_code'] = $error_code;
        }
        
        \Flight::json($response);
    }

    protected function error($error_code, $status = 400) {
        \Flight::json([
            'success' => false,
            'error_code' => $error_code
        ], $status);
    }

    protected function success($data = []) {
        \Flight::json(array_merge(
            ['success' => true],
            $data
        ));
    }

    protected function logMessage($message, $controller, $type = 'INFO') {
        try {
            $logDir = __DIR__ . '/../../logs';
            $logFile = $logDir . "/$controller.log";
            
            // Create log directory if it doesn't exist
            if (!file_exists($logDir)) {
                if (!mkdir($logDir, 0777, true)) {
                    error_log("Failed to create log directory: " . $logDir);
                    return;
                }
            }
            
            // Check if directory is writable
            if (!is_writable($logDir)) {
                error_log("Log directory is not writable: " . $logDir);
                return;
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = "[$timestamp][$type] $message" . PHP_EOL;
            
            // Write to log file
            if (file_put_contents($logFile, $formattedMessage, FILE_APPEND) === false) {
                error_log("Failed to write to log file: " . $logFile);
            }
            
            // Also output to error_log for debugging
            error_log($formattedMessage);
        } catch (\Exception $e) {
            error_log("Error in logMessage: " . $e->getMessage());
        }
    }    
}
