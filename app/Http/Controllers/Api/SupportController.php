<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:trip,delivery,payment,account,safety,other'],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        $supportRequest = SupportRequest::create($data + [
            'user_id' => $request->user()->id,
            'status' => 'open',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $supportRequest->id,
                'reference' => 'SUP-'.str_pad((string) $supportRequest->id, 6, '0', STR_PAD_LEFT),
                'status' => $supportRequest->status,
            ],
        ], 201);
    }
}
