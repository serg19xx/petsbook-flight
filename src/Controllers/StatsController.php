<?php

namespace App\Controllers;

use PDO;
use Flight;
use App\Constants\ResponseCodes;
use App\Utils\Logger;


class StatsController extends BaseController {
    private static $logFile = __DIR__ . '/../../logs/stats.log';
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function visit() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            Logger::info($requestBody,'StatsController');

            $language = $data['language'] ?? null;
            $referrer = $data['referrer'] ?? null;
            $visitTime = $data['visitTime'] ?? null;
            $timeZone = $data['timeZone'] ?? null;
            $userAgent = $data['userAgent'] ?? null;
            $url = $data['url'] ?? null;
            
            $dt = $visitTime ? new \DateTime($visitTime) : null;
            $mysqlTime = $dt ? $dt->format('Y-m-d H:i:s') : null;

            $ip = $_SERVER['REMOTE_ADDR'];
            $country = $this->getCountryByIp($ip);
            
            // Save visit data
            $stmt = $this->db->prepare(
                'INSERT INTO stats_visit (ip, country, visit_time, timezone, language, referrer, user_agent) 
                 VALUES (?, ?, ?, ?, ?, NULLIF(?,\'\'), ?)'
            );
            
            $stmt->execute([$ip, $country, $visitTime, $timeZone, $language, $referrer, $userAgent]);

            return Flight::json([
                'status' => 200,
                'error_code' => 'VISIT_RECORDED',
                'data' => [
                    'country' => $country
                ]
            ], 200);
        } catch (\Exception $e) {
            Logger::error('Stats visit error: ' . $e->getMessage(),'StatsController');
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    private function getCountryByIp($ip) {
        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country");
            if ($response === false) {
                error_log('IP API request failed for IP: ' . $ip);
                return 'Unknown';
            }
            
            $data = json_decode($response, true);
            return isset($data['country']) ? $data['country'] : 'Unknown';
        } catch (\Exception $e) {
            Logger::error('IP API error: ' . $e->getMessage(),'StatsController');
            return 'Unknown';
        }
    }

}