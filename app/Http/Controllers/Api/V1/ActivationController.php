<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Activations\Actions\DeactivateInstallation;
use App\Domain\Activations\Actions\ExchangeActivationAuthorization;
use App\Domain\Activations\Actions\ReadEntitlements;
use App\Domain\Activations\Models\InstallationToken;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeActivationRequest;
use App\Http\Resources\ActivationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ActivationController extends Controller
{
    public function store(ExchangeActivationRequest $request, Product $product, ExchangeActivationAuthorization $exchange): JsonResponse
    {
        $data = $request->validated();
        $result = $exchange->handle($product, $data['authorization_code'], $data['request_id'], $request->safe()->only(['machine_hash', 'platform', 'plugin_version']));

        return response()->json([
            'data' => (new ActivationResource($result->activation))->resolve($request),
            'installation_token' => $result->plainTextToken,
            'token_expires_at' => $result->expiresAt->toIso8601String(),
        ], $result->created ? 201 : 200);
    }

    public function entitlements(Request $request, Product $product, ReadEntitlements $read): ActivationResource
    {
        return new ActivationResource($read->handle($this->credential($request), $product));
    }

    public function destroy(Request $request, Product $product, string $activation, DeactivateInstallation $deactivate): Response
    {
        $deactivate->handle($this->credential($request), $product, $activation);

        return response()->noContent();
    }

    private function credential(Request $request): InstallationToken
    {
        return $request->attributes->get('installation_credential');
    }
}
