<?php
// app/Helpers/ApiResponse.php

namespace App\Models;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Respuesta exitosa
     * 
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200,$validations=[]): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            "validations"=>$validations,
        ], $statusCode);
    }

    /**
     * Respuesta de error
     * 
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function error($message = 'Error', $statusCode = 500): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Respuesta de validación fallida
     * 
     * @param string $message
     * @param array $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function validationError($message = 'Validation Error', $errors = [], $statusCode = 422): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}
