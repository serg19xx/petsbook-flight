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
}
