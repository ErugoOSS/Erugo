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
     * Get available subscription plans
     */
    public function plans(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->getPlans();

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
     * Change subscription plan (between paid plans)
     */
    public function changePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required|string|in:pro,business',
        ]);

        try {
            $result = $this->cloudConnectService->changePlan($validated['plan']);

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
     * Cancel subscription at period end
     */
    public function cancelSubscription(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->cancelSubscription();

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
     * Reactivate a cancelled subscription
     */
    public function reactivateSubscription(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->reactivateSubscription();

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

            // Automatically connect the tunnel after creating/reclaiming the instance
            $connectResult = null;
            $connectError = null;
            try {
                $connectResult = $this->cloudConnectService->connect();
                $message .= ' and connected';
            } catch (Exception $connectException) {
                // Don't fail the whole request if connect fails - instance was still created
                $connectError = $connectException->getMessage();
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => array_merge($result, [
                    'connected' => $connectResult !== null,
                    'connect_error' => $connectError,
                ]),
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

    /**
     * Get user usage statistics
     */
    public function usage(): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->getUserUsage();

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
     * Update user profile
     */
    public function updateUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        try {
            $result = $this->cloudConnectService->updateUser($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
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
     * Request password reset email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $result = $this->cloudConnectService->forgotPassword($validated['email']);

            return response()->json([
                'status' => 'success',
                'message' => 'If an account exists with that email, a password reset link has been sent.',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            // Don't reveal if email exists or not
            return response()->json([
                'status' => 'success',
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ]);
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
        ]);

        try {
            $result = $this->cloudConnectService->resetPassword(
                $validated['token'],
                $validated['password'],
                $validated['password_confirmation']
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset successfully',
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
     * Get a specific instance
     */
    public function getInstance(string $instanceId): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->getInstance($instanceId);

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
     * Update an instance
     */
    public function updateInstance(Request $request, string $instanceId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'subdomain' => 'sometimes|string|min:3|max:63|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
        ]);

        try {
            $result = $this->cloudConnectService->updateInstance($instanceId, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Instance updated successfully',
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
     * Delete an instance
     */
    public function deleteInstance(string $instanceId): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->deleteInstance($instanceId);

            return response()->json([
                'status' => 'success',
                'message' => 'Instance deleted successfully',
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
     * Regenerate instance token
     */
    public function regenerateToken(string $instanceId): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->regenerateInstanceToken($instanceId);

            return response()->json([
                'status' => 'success',
                'message' => 'Instance token regenerated successfully',
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
     * Link an existing instance to this Erugo installation
     */
    public function linkInstance(string $instanceId): JsonResponse
    {
        try {
            $result = $this->cloudConnectService->linkInstance($instanceId);

            return response()->json([
                'status' => 'success',
                'message' => 'Instance linked successfully',
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
     * Create billing portal session
     */
    public function billingPortal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'return_url' => 'sometimes|string|url',
        ]);

        try {
            // Default return URL to the current app URL
            $returnUrl = $validated['return_url'] ?? config('app.url', url('/'));
            $result = $this->cloudConnectService->createBillingPortal($returnUrl);

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

