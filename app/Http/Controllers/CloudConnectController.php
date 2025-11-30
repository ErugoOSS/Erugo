<?php

namespace App\Http\Controllers;

use App\Services\CloudConnectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class CloudConnectController extends Controller
{
    protected CloudConnectService $cloudConnectService;

    public function __construct(CloudConnectService $cloudConnectService)
    {
        $this->cloudConnectService = $cloudConnectService;
    }

    /**
     * Get current cloud connect status and capabilities
     */
    public function status(Request $request): JsonResponse
    {
        try {
            // Pass refresh=true to force fetching latest profile from API
            $refresh = $request->boolean('refresh', false);
            $status = $this->cloudConnectService->getStatus($refresh);

            return response()->json([
                'status' => 'success',
                'data' => $status,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register a new cloud account
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
            'accept_terms' => 'required|boolean|accepted',
            'accept_privacy' => 'required|boolean|accepted',
            'accept_marketing' => 'boolean',
        ]);

        try {
            $result = $this->cloudConnectService->register($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Account created successfully. Please check your email to verify your account.',
                'data' => $result,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Login to cloud account
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->cloudConnectService->login(
                $validated['email'],
                $validated['password']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $result['user'] ?? null,
                    'subscription_status' => $result['user']['subscription_status'] ?? 'none',
                    'subscription_plan' => $result['user']['subscription_plan'] ?? null,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Logout from cloud account
     */
    public function logout(): JsonResponse
    {
        try {
            $this->cloudConnectService->logout();

            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerification(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->resendVerificationEmail();

            return response()->json([
                'status' => 'success',
                'message' => 'Verification email sent',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get subscription status
     */
    public function subscription(): JsonResponse
    {
        try {
            $subscription = $this->cloudConnectService->getSubscription();

            return response()->json([
                'status' => 'success',
                'data' => $subscription,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create Stripe checkout session
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|in:pro,business',
        ]);

        try {
            $result = $this->cloudConnectService->createCheckout($validated['plan']);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check subdomain availability
     */
    public function checkSubdomain(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subdomain' => 'required|string|min:3|max:63|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
        ]);

        try {
            $result = $this->cloudConnectService->checkSubdomain($validated['subdomain']);

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create instance and get connection credentials
     */
    public function createInstance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subdomain' => 'required|string|min:3|max:63|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
            'confirm_reclaim' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->cloudConnectService->createInstance(
                $validated['name'],
                $validated['subdomain'],
                $validated['confirm_reclaim'] ?? false
            );

            $statusCode = ($result['reclaimed'] ?? false) ? 200 : 201;
            $message = ($result['reclaimed'] ?? false) ? 'Instance reclaimed successfully' : 'Instance created successfully';

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $result,
            ], $statusCode);
        } catch (Exception $e) {
            // Check if this is a SUBDOMAIN_OWNED_BY_USER error (409 from API)
            $errorData = json_decode($e->getMessage(), true);
            if (is_array($errorData) && ($errorData['code'] ?? null) === 'SUBDOMAIN_OWNED_BY_USER') {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SUBDOMAIN_OWNED_BY_USER',
                    'message' => $errorData['message'] ?? 'You already own an instance with this subdomain',
                    'data' => [
                        'subdomain' => $errorData['subdomain'] ?? $validated['subdomain'],
                        'existing_instance_id' => $errorData['existing_instance_id'] ?? null,
                        'existing_instance_name' => $errorData['existing_instance_name'] ?? null,
                        'requires_confirmation' => true,
                    ],
                ], 409);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get list of instances
     */
    public function instances(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->getInstances();

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Connect the tunnel
     */
    public function connect(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->connect();

            return response()->json([
                'status' => 'success',
                'message' => 'Tunnel connected successfully',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Disconnect the tunnel
     */
    public function disconnect(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->disconnect();

            return response()->json([
                'status' => 'success',
                'message' => 'Tunnel disconnected',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tunnel status from cloud
     */
    public function tunnelStatus(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->getTunnelStatus();

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

