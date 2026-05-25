<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Media\StoreMediaRequest;
use App\Http\Resources\Api\V1\MediaResource;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\RoomCategory;
use App\Services\Hotel\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MediaController extends Controller
{
    public function __construct(
        protected MediaService $mediaService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(RoomCategory $roomCategory)
    {
        Gate::authorize('viewAny', [Media::class, $roomCategory]);
        $media = $roomCategory->media;
        return MediaResource::collection($media);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMediaRequest $request, RoomCategory $roomCategory)
    {
        Gate::authorize('create', [Media::class, $roomCategory]);
        $data = $request->validated();       
        $media = $this->mediaService->createMedia(
            $roomCategory,
            $request->file('file')
        );
        return new MediaResource($media);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Media $media)
    {
        Gate::authorize('delete', $media);
        $this->mediaService->deleteMedia($media);
        return response()->noContent();
    }
}
