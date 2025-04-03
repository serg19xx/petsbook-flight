<?php

namespace App\Controllers;

class BaseController {
    protected function response($data, $status = 200) {
        \Flight::json($data, $status);
    }

    protected function error($message, $status = 400) {
        \Flight::json([
            'status' => 'error',
            'message' => $message
        ], $status);
    }

    protected function success($data = [], $message = 'Success') {
        \Flight::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }
}
