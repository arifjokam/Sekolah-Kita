<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadNewsArticleImageRequest;
use App\Models\NewsArticle;
use App\Models\User;
use App\Services\NewsArticleBodyImageService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Upload endpoint for the article rich text editor.
 *
 * The response is deliberately minimal: the safe public URL, the media
 * identifier and the dimensions the editor needs. Internal storage keys, the
 * private object key and the original filename are never returned, and the
 * route only exists behind the administrator middleware stack.
 */
class NewsArticleImageController extends Controller
{
    /**
     * Upload an image before the article exists, for the create screen.
     */
    public function store(
        UploadNewsArticleImageRequest $request,
        NewsArticleBodyImageService $images,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return $this->respond($images->upload($actor, null, $request->file('image')));
    }

    /**
     * Upload an image while editing an existing article.
     */
    public function storeForArticle(
        UploadNewsArticleImageRequest $request,
        NewsArticle $newsArticle,
        NewsArticleBodyImageService $images,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return $this->respond($images->upload($actor, $newsArticle, $request->file('image')));
    }

    /**
     * @param  array{id: int, url: string, width: int|null, height: int|null, mime_type: string, max_bytes: int}  $image
     */
    private function respond(array $image): JsonResponse
    {
        return response()->json($image, Response::HTTP_CREATED);
    }
}
