<?php

namespace App\AuthProviders;
use App\Models\AuthProvider as AuthProviderModel;
use App\AuthProviders\AuthProviderUser;
use Illuminate\Validation\Validator;

interface AuthProviderInterface
{
    public function __construct(AuthProviderModel $provider);

    public function redirect();

    public function handleCallback(): AuthProviderUser;

    public static function getIcon(): string;

    public static function getName(): string;

    public static function getDescription(): string;

    public static function getValidator(array $data): Validator;

    public static function getEmptyProviderConfig(): array;

    public static function getAdvancedProviderConfig(): array;

    public static function getAdvancedConfigKeys(): array;

    public static function getInformationUrl(): ?string;
}

