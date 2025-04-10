<?php

namespace App\Traits;

trait ApiResponder
{
    /**
     * Return a success JSON response.
     *
     * @param  array|string  $data
     * @param  string  $message
     * @param  int  $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data = [], string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  array|string|null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message = 'Error', int $code = 400, $data = null)
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a validation error JSON response.
     *
     * @param  \Illuminate\Contracts\Validation\Validator|array  $errors
     * @param  string  $message
     * @param  int  $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationError($errors, string $message = 'Validation failed', int $code = 422)
    {
        return $this->error($message, $code, [
            'errors' => $errors instanceof \Illuminate\Contracts\Validation\Validator ? $errors->errors() : $errors
        ]);
    }

    /**
     * Return an unauthorized JSON response.
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function unauthorised(string $message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }
}